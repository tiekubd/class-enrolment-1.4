<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Shared rules for the Class Enrolment module. Every page and process script
 * goes through these functions so the UI and the server enforce the same rules.
 */

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseEnrolmentGateway;

/**
 * People the current user may enrol: themself and any member of a family in
 * which they are an adult with child data access, limited to people who are
 * active (Full or Expected) students in the given school year.
 *
 * Adults are included only when they are themselves students, which supports
 * schools where parents also take classes, without exposing non-student adults.
 *
 * @return array gibbonPersonID => ['gibbonPersonID', 'preferredName', 'surname', 'gibbonYearGroupID']
 */
function classEnrolmentGetAccessiblePeople(Connection $db, $gibbonSchoolYearID, $gibbonPersonIDCurrent)
{
    $data = [
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'today'              => date('Y-m-d'),
        'self'               => $gibbonPersonIDCurrent,
        'adultChild'         => $gibbonPersonIDCurrent,
        'adultAdult'         => $gibbonPersonIDCurrent,
    ];

    $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname, gibbonStudentEnrolment.gibbonYearGroupID
            FROM gibbonPerson
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID)
            WHERE (gibbonPerson.status='Full' OR gibbonPerson.status='Expected')
            AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=:today)
            AND (
                gibbonPerson.gibbonPersonID=:self
                OR gibbonPerson.gibbonPersonID IN (
                    SELECT gibbonFamilyChild.gibbonPersonID
                    FROM gibbonFamilyAdult
                    JOIN gibbonFamilyChild ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID)
                    WHERE gibbonFamilyAdult.gibbonPersonID=:adultChild AND gibbonFamilyAdult.childDataAccess='Y'
                )
                OR gibbonPerson.gibbonPersonID IN (
                    SELECT member.gibbonPersonID
                    FROM gibbonFamilyAdult
                    JOIN gibbonFamilyAdult AS member ON (member.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID)
                    WHERE gibbonFamilyAdult.gibbonPersonID=:adultAdult AND gibbonFamilyAdult.childDataAccess='Y'
                )
            )
            GROUP BY gibbonPerson.gibbonPersonID
            ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

    $people = [];
    foreach ($db->select($sql, $data)->fetchAll() as $person) {
        $people[$person['gibbonPersonID']] = $person;
    }

    return $people;
}

/**
 * Works out whether the enrolment window is open right now.
 *
 * @return array ['status' => 'open'|'notYetOpen'|'closed'|'invalid', 'open' => string, 'close' => string]
 */
function classEnrolmentGetWindow(SettingGateway $settingGateway)
{
    $now = date('Y-m-d H:i');
    $open = trim((string) $settingGateway->getSettingByScope('Class Enrolment', 'openParentEnrolment'));
    $close = trim((string) $settingGateway->getSettingByScope('Class Enrolment', 'closeParentEnrolment'));

    if ($open !== '' && $close !== '' && $open >= $close) {
        $status = 'invalid';
    } elseif ($open !== '' && $now < $open) {
        $status = 'notYetOpen';
    } elseif ($close !== '' && $now > $close) {
        $status = 'closed';
    } else {
        $status = 'open';
    }

    return ['status' => $status, 'open' => $open, 'close' => $close];
}

/**
 * Classes a student in the given year group may enrol in, keyed by gibbonCourseClassID.
 * Each row includes enrolmentMin, enrolmentMax and the current studentCount.
 */
function classEnrolmentGetEnrolableClasses(CourseEnrolmentGateway $courseEnrolmentGateway, $gibbonSchoolYearID, $gibbonYearGroupID)
{
    if (empty($gibbonYearGroupID)) {
        return [];
    }

    $classes = [];
    foreach ($courseEnrolmentGateway->selectEnrolableClassesByYearGroup($gibbonSchoolYearID, $gibbonYearGroupID)->fetchAll() as $class) {
        $classes[$class['gibbonCourseClassID']] = $class;
    }

    return $classes;
}

/**
 * A class is full once it has reached its maximum, not only once it has passed it.
 */
function classEnrolmentIsFull($enrolmentMax, $studentCount)
{
    return is_numeric($enrolmentMax) && intval($studentCount) >= intval($enrolmentMax);
}

/**
 * Checks whether the current user may remove a student from a class.
 *
 * @return array ['error' => return code or '', 'enrolment' => gibbonCourseClassPerson row or null]
 */
function classEnrolmentCheckUnenrolment($container, $session, $gibbonPersonID, $gibbonCourseClassID)
{
    $settingGateway = $container->get(SettingGateway::class);
    $courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    if (empty($gibbonPersonID) || empty($gibbonCourseClassID)) {
        return ['error' => 'error1', 'enrolment' => null];
    }

    if (classEnrolmentGetWindow($settingGateway)['status'] != 'open') {
        return ['error' => 'error3', 'enrolment' => null];
    }

    if ($settingGateway->getSettingByScope('Class Enrolment', 'allowParentUnenrolment') == 'N') {
        return ['error' => 'error5', 'enrolment' => null];
    }

    $people = classEnrolmentGetAccessiblePeople($container->get(Connection::class), $gibbonSchoolYearID, $session->get('gibbonPersonID'));
    if (empty($people[$gibbonPersonID])) {
        return ['error' => 'error0', 'enrolment' => null];
    }

    $enrolment = $courseEnrolmentGateway->selectBy(['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $gibbonPersonID])->fetch();
    if (empty($enrolment) || $enrolment['role'] != 'Student') {
        return ['error' => 'error2', 'enrolment' => null];
    }

    $enrolableClasses = classEnrolmentGetEnrolableClasses($courseEnrolmentGateway, $gibbonSchoolYearID, $people[$gibbonPersonID]['gibbonYearGroupID']);
    if (empty($enrolableClasses[$gibbonCourseClassID])) {
        return ['error' => 'error5', 'enrolment' => null];
    }

    return ['error' => '', 'enrolment' => $enrolment];
}

/**
 * Return messages specific to unenrolment, shared by the enrolment and delete pages.
 */
function classEnrolmentUnenrolmentReturns()
{
    return [
        'error3' => __m('The window for adding and editing class enrolments is currently closed.'),
        'error5' => __m('This class cannot be removed here. Please contact the school.'),
    ];
}

/**
 * How many more students a class needs to reach its minimum enrolment, or 0.
 * The minimum is advisory: it is shown to families but never blocks changes.
 */
function classEnrolmentStudentsNeeded($enrolmentMin, $studentCount)
{
    if (!is_numeric($enrolmentMin)) {
        return 0;
    }

    return max(0, intval($enrolmentMin) - intval($studentCount));
}

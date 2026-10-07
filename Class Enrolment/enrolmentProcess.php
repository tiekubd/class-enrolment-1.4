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

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseEnrolmentGateway;

include '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$gibbonCourseClassIDs = $_POST['gibbonCourseClassID'] ?? [];
$gibbonCourseClassIDs = is_array($gibbonCourseClassIDs) ? array_unique(array_filter($gibbonCourseClassIDs)) : [];
$gibbonPersonID = $_POST['gibbonPersonID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Enrolment/enrolment.php&gibbonPersonID='.urlencode($gibbonPersonID);

if (isActionAccessible($guid, $connection2, '/modules/Class Enrolment/enrolment.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

if (empty($gibbonPersonID) || empty($gibbonCourseClassIDs)) {
    header("Location: {$URL}&return=error1");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

// The enrolment window is enforced here, not just on the page
$window = classEnrolmentGetWindow($settingGateway);
if ($window['status'] != 'open') {
    header("Location: {$URL}&return=error3");
    exit;
}

// The student must be an active student this user may act for
$people = classEnrolmentGetAccessiblePeople($container->get(Connection::class), $gibbonSchoolYearID, $session->get('gibbonPersonID'));
if (empty($people[$gibbonPersonID])) {
    header("Location: {$URL}&return=error0");
    exit;
}

// Every class must be one offered to this student's year group in the current school year
$enrolableClasses = classEnrolmentGetEnrolableClasses($courseEnrolmentGateway, $gibbonSchoolYearID, $people[$gibbonPersonID]['gibbonYearGroupID']);
foreach ($gibbonCourseClassIDs as $gibbonCourseClassID) {
    if (empty($enrolableClasses[$gibbonCourseClassID])) {
        header("Location: {$URL}&return=error4");
        exit;
    }
}

$useDatabaseLocking = $settingGateway->getSettingByScope('Class Enrolment', 'useDatabaseLocking') == 'Y';
$partialFail = false;
$classFull = false;

// Lock the enrolment table so two families cannot both take the last place
if ($useDatabaseLocking) {
    try {
        $connection2->query('LOCK TABLES gibbonCourseClassPerson WRITE, gibbonPerson READ');
    } catch (PDOException $e) {
        header("Location: {$URL}&return=error2");
        exit;
    }
}

try {
    foreach ($gibbonCourseClassIDs as $gibbonCourseClassID) {
        $courseEnrolment = $courseEnrolmentGateway->selectBy(['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $gibbonPersonID])->fetch();

        // Already enrolled as a student: nothing to do
        if (!empty($courseEnrolment['role']) && $courseEnrolment['role'] == 'Student') {
            continue;
        }

        // Count again inside the lock, as the page may be out of date
        $studentCount = $courseEnrolmentGateway->getClassStudentCount($gibbonCourseClassID, false);
        if (classEnrolmentIsFull($enrolableClasses[$gibbonCourseClassID]['enrolmentMax'], $studentCount)) {
            $classFull = true;
            continue;
        }

        if (empty($courseEnrolment)) {
            $inserted = $courseEnrolmentGateway->insert([
                'gibbonCourseClassID' => $gibbonCourseClassID,
                'gibbonPersonID'      => $gibbonPersonID,
                'role'                => 'Student',
                'dateEnrolled'        => date('Y-m-d'),
            ]);
            $partialFail |= !$inserted;
        } elseif ($courseEnrolment['role'] == 'Student - Left') {
            // Re-enrolling a student who previously left this class
            $updated = $courseEnrolmentGateway->update($courseEnrolment['gibbonCourseClassPersonID'], [
                'role'           => 'Student',
                'dateEnrolled'   => date('Y-m-d'),
                'dateUnenrolled' => null,
            ]);
            $partialFail |= !$updated;
        } else {
            // Any other role (Teacher, Assistant, etc.) is left untouched
            $partialFail = true;
        }
    }
} finally {
    if ($useDatabaseLocking) {
        $connection2->query('UNLOCK TABLES');
    }
}

if ($classFull) {
    $URL .= '&return=warning1';
} elseif ($partialFail) {
    $URL .= '&return=error2';
} else {
    $URL .= '&return=success0';
}

header("Location: {$URL}");

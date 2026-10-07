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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseEnrolmentGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Enrolment/enrolment.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__m('Enrolment'));

    $page->return->addReturns(array_merge(classEnrolmentUnenrolmentReturns(), [
        'error4'   => __m('One or more of the selected classes is not available for this student.'),
        'warning1' => __m('Your request was partially successful: one or more classes were full and could not be added.'),
    ]));

    $settingGateway = $container->get(SettingGateway::class);
    $window = classEnrolmentGetWindow($settingGateway);

    if ($window['status'] == 'invalid') {
        $page->addError(__m('The settings for class enrolments are invalid'));
        return;
    }

    if ($window['status'] == 'notYetOpen') {
        $page->addWarning(__m('The window for adding and editing class enrolments will open at {time} on {date}.', ['date' => Format::date(substr($window['open'], 0, 10)), 'time' => substr($window['open'], 11, 5)]));
        return;
    }

    if ($window['status'] == 'closed') {
        $page->addWarning(__m('The window for adding and editing class enrolments is currently closed.'));
        return;
    }

    if (!empty($window['close'])) {
        $page->addMessage(__m('The window for adding and editing class enrolments is currently open, but will close at {time} on {date}.', ['date' => Format::date(substr($window['close'], 0, 10)), 'time' => substr($window['close'], 11, 5)]));
    } else {
        $page->addMessage(__m('The window for adding and editing class enrolments is currently open.'));
    }

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

    // People this user may enrol: active students only
    $people = classEnrolmentGetAccessiblePeople($container->get(Connection::class), $gibbonSchoolYearID, $session->get('gibbonPersonID'));

    if (empty($people)) {
        $page->addMessage(__m('There are no students in your family who can be enrolled in classes this school year.'));
        return;
    }

    // SELECT STUDENT
    $form = Form::create('selectFamily', $session->get('absoluteURL').'/index.php', 'get');
    $form->addHiddenValue('q', '/modules/Class Enrolment/enrolment.php');
    $form->setTitle(__m('Select Student'));

    $peopleOptions = array_map(function ($person) {
        return Format::name('', $person['preferredName'], $person['surname'], 'Student', true, true);
    }, $people);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __m('Student'));
        $row->addSelect('gibbonPersonID')
            ->fromArray($peopleOptions)
            ->selected($gibbonPersonID)
            ->placeholder()
            ->required();

    $row = $form->addRow();
        $row->addSubmit(__('Go'));

    echo $form->getOutput();

    if (empty($gibbonPersonID)) {
        return;
    }

    // CHECK ACCESS TO STUDENT
    if (empty($people[$gibbonPersonID])) {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        return;
    }

    $student = $people[$gibbonPersonID];
    $courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);
    $enrolableClasses = classEnrolmentGetEnrolableClasses($courseEnrolmentGateway, $gibbonSchoolYearID, $student['gibbonYearGroupID']);
    $allowUnenrolment = $settingGateway->getSettingByScope('Class Enrolment', 'allowParentUnenrolment') != 'N';

    // CURRENT ENROLMENT
    $criteria = $courseEnrolmentGateway->newQueryCriteria(true)
        ->sortBy('roleSortOrder')
        ->sortBy(['course', 'class'])
        ->fromPOST();

    $enrolment = $courseEnrolmentGateway->queryCourseEnrolmentByPerson($criteria, $gibbonSchoolYearID, $gibbonPersonID);

    $enrolledClassIDs = [];
    foreach ($enrolment as $class) {
        if ($class['role'] == 'Student') {
            $enrolledClassIDs[] = $class['gibbonCourseClassID'];
        }
    }

    // ADD ENROLMENT FORM
    $form = Form::create('enrolment', $session->get('absoluteURL').'/modules/Class Enrolment/enrolmentProcess.php');
    $form->setTitle(__m('Add Enrolment'));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);

    $classOptions = [];
    $disabledClasses = [];
    foreach ($enrolableClasses as $gibbonCourseClassID => $class) {
        $label = $class['courseName'].' ('.__('Class').' '.$class['class'].')';
        $teacherName = Format::name('', $class['preferredName'], $class['surname'], 'Staff');
        if (!empty($teacherName)) {
            $label .= ' - '.$teacherName;
        }

        if (in_array($gibbonCourseClassID, $enrolledClassIDs)) {
            $label .= ' ('.__m('Enrolled').')';
            $disabledClasses[] = $gibbonCourseClassID;
        } elseif (classEnrolmentIsFull($class['enrolmentMax'], $class['studentCount'])) {
            $label .= ' ('.__m('Full').')';
            $disabledClasses[] = $gibbonCourseClassID;
        } elseif ($needed = classEnrolmentStudentsNeeded($class['enrolmentMin'], $class['studentCount'])) {
            $label .= ' ('.($needed == 1
                ? __m('needs 1 more student to run')
                : __m('needs {count} more students to run', ['count' => $needed])).')';
        }

        $classOptions[$gibbonCourseClassID] = $label;
    }

    if (empty($classOptions)) {
        $form->addRow()->addAlert(__m('There are no classes available for this student\'s year group.'), 'message');
    } else {
        $row = $form->addRow();
            $row->addLabel('gibbonCourseClassID', __('Classes'));
            $row->addSelect('gibbonCourseClassID')
                ->fromArray([__m('Enrolable Classes') => $classOptions])
                ->selectMultiple()
                ->required();

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();
    }

    echo $form->getOutput();

    // Disable full and already-enrolled classes in the picker (the server checks again on submit)
    if (!empty($disabledClasses)) {
        echo '<script type="text/javascript">';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo 'var ids = '.json_encode(array_values(array_map('strval', $disabledClasses))).';';
        echo 'document.querySelectorAll("#gibbonCourseClassID option").forEach(function(option) {';
        echo 'if (ids.indexOf(option.value) !== -1) { option.disabled = true; }';
        echo '});';
        echo '});';
        echo '</script>';
    }

    // CURRENT ENROLMENT TABLE
    $table = DataTable::create('currentEnrolment');
    $table->setTitle(__m('Current Enrolment'));

    if (!$allowUnenrolment) {
        $table->setDescription(__m('Please contact the school to remove a class from this timetable.'));
    }

    $table->addColumn('courseClass', __('Class Code'))
        ->sortable(['course', 'class'])
        ->format(Format::using('courseClassName', ['course', 'class']));
    $table->addColumn('courseName', __('Course'));
    $table->addColumn('role', __('Role'))->translatable();

    $table->addActionColumn()
        ->addParam('gibbonCourseClassID')
        ->addParam('gibbonPersonID', $gibbonPersonID)
        ->format(function ($class, $actions) use ($allowUnenrolment, $enrolableClasses) {
            // Only student enrolments in classes open to this year group can be removed here
            if (!$allowUnenrolment || $class['role'] != 'Student' || empty($enrolableClasses[$class['gibbonCourseClassID']])) {
                return;
            }

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Class Enrolment/enrolment_delete.php');
        });

    echo $table->render($enrolment);
}

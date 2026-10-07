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

use Gibbon\Forms\Prefab\DeleteForm;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Enrolment/enrolment_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

    $check = classEnrolmentCheckUnenrolment($container, $session, $gibbonPersonID, $gibbonCourseClassID);
    $messages = classEnrolmentUnenrolmentReturns();

    if ($check['error'] == 'error1') {
        $page->addError(__('You have not specified one or more required parameters.'));
    } elseif ($check['error'] == 'error0' || $check['error'] == 'error2') {
        $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    } elseif (!empty($check['error'])) {
        $page->addError($messages[$check['error']]);
    } else {
        $form = DeleteForm::createForm($session->get('absoluteURL').'/modules/Class Enrolment/enrolment_deleteProcess.php?gibbonCourseClassID='.urlencode($gibbonCourseClassID).'&gibbonPersonID='.urlencode($gibbonPersonID));
        echo $form->getOutput();
    }
}

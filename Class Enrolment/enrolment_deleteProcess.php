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

use Gibbon\Domain\Timetable\CourseEnrolmentGateway;

include '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

// On failure go back to the enrolment page for this student; that page shows the reason
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Enrolment/enrolment.php&gibbonPersonID='.urlencode($gibbonPersonID);

if (isActionAccessible($guid, $connection2, '/modules/Class Enrolment/enrolment_delete.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$check = classEnrolmentCheckUnenrolment($container, $session, $gibbonPersonID, $gibbonCourseClassID);
if (!empty($check['error'])) {
    header("Location: {$URL}&return={$check['error']}");
    exit;
}

$courseEnrolmentGateway = $container->get(CourseEnrolmentGateway::class);
$updated = $courseEnrolmentGateway->update($check['enrolment']['gibbonCourseClassPersonID'], [
    'role'           => 'Student - Left',
    'dateUnenrolled' => date('Y-m-d'),
]);

header("Location: {$URL}&return=".($updated ? 'success0' : 'error2'));

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

use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Enrolment/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Enrolment/settings.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);

// Combine the separate date and time fields into 'Y-m-d H:i', or blank for no limit
$dateTimeValue = function ($name) {
    if (empty($_POST[$name.'Date'])) {
        return '';
    }

    $date = Format::dateConvert($_POST[$name.'Date']);
    $time = !empty($_POST[$name.'Time']) ? substr($_POST[$name.'Time'], 0, 5) : '00:00';

    return $date.' '.$time;
};

$values = [
    'openParentEnrolment'    => $dateTimeValue('openParentEnrolment'),
    'closeParentEnrolment'   => $dateTimeValue('closeParentEnrolment'),
    'useDatabaseLocking'     => $_POST['useDatabaseLocking'] ?? '',
    'allowParentUnenrolment' => $_POST['allowParentUnenrolment'] ?? '',
];

// Validate required Y/N fields
foreach (['useDatabaseLocking', 'allowParentUnenrolment'] as $name) {
    if ($values[$name] != 'Y' && $values[$name] != 'N') {
        header("Location: {$URL}&return=error1");
        exit;
    }
}

// The window must close after it opens
if (!empty($values['openParentEnrolment']) && !empty($values['closeParentEnrolment']) && $values['openParentEnrolment'] >= $values['closeParentEnrolment']) {
    header("Location: {$URL}&return=error3");
    exit;
}

$partialFail = false;
foreach ($values as $name => $value) {
    $updated = $settingGateway->updateSettingByScope('Class Enrolment', $name, $value);
    $partialFail |= !$updated;
}

header("Location: {$URL}&return=".($partialFail ? 'warning1' : 'success0'));

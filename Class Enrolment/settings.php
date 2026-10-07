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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Class Enrolment/settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $page->breadcrumbs->add(__('Manage Settings'));

    $page->return->addReturns(['error3' => __m('The close date and time must be after the open date and time.')]);

    $settingGateway = $container->get(SettingGateway::class);

    // FORM
    $form = Form::create('settings', $session->get('absoluteURL').'/modules/Class Enrolment/settingsProcess.php');
    $form->setTitle(__('Settings'));

    $form->addHiddenValue('address', $session->get('address'));

    $setting = $settingGateway->getSettingByScope('Class Enrolment', 'openParentEnrolment', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
        $col = $row->addColumn('openParentEnrolment')->setClass('inline');
        $col->addDate('openParentEnrolmentDate')->setValue(Format::date(substr($setting['value'], 0, 10)))->addClass('mr-2');
        $col->addTime('openParentEnrolmentTime')->setValue(substr($setting['value'], 11, 5));

    $setting = $settingGateway->getSettingByScope('Class Enrolment', 'closeParentEnrolment', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
        $col = $row->addColumn('closeParentEnrolment')->setClass('inline');
        $col->addDate('closeParentEnrolmentDate')->setValue(Format::date(substr($setting['value'], 0, 10)))->addClass('mr-2');
        $col->addTime('closeParentEnrolmentTime')->setValue(substr($setting['value'], 11, 5));

    $setting = $settingGateway->getSettingByScope('Class Enrolment', 'useDatabaseLocking', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $setting = $settingGateway->getSettingByScope('Class Enrolment', 'allowParentUnenrolment', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __m($setting['nameDisplay']))->description(__m($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}

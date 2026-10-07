<?php
//USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

//v1.0.00
$count++;
$sql[$count][0] = "1.0.00";
$sql[$count][1] = "-- First version, nothing to update";


//v1.0.01
$count++;
$sql[$count][0] = "1.0.01";
$sql[$count][1] = "";

//v1.0.02
$count++;
$sql[$count][0] = "1.0.02";
$sql[$count][1] = "";

//v1.1.00
$count++;
$sql[$count][0] = "1.1.00";
$sql[$count][1] = "";

//v1.1.01
$count++;
$sql[$count][0] = "1.1.01";
$sql[$count][1] = "";

//v1.2.00
$count++;
$sql[$count][0] = "1.2.00";
$sql[$count][1] = "
INSERT INTO `gibbonSetting` (`gibbonSettingID` ,`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES (NULL , 'Class Enrolment', 'useDatabaseLocking', 'Use Database Locking', 'Ensures fidelity of minimum and maximum enrolment, but comes with a performance cost.', 'Y');end
";

//v1.3.00
++$count;
$sql[$count][0] = '1.3.00';
$sql[$count][1] = "
UPDATE gibbonModule SET author='Gibbon Foundation', url='https://gibbonedu.org' WHERE name='Class Enrolment';end
";

//v1.3.01
$count++;
$sql[$count][0] = "1.3.01";
$sql[$count][1] = "";

//v1.4.00
$count++;
$sql[$count][0] = "1.4.00";
$sql[$count][1] = "
INSERT INTO `gibbonSetting` (`gibbonSettingID` ,`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES (NULL , 'Class Enrolment', 'allowParentUnenrolment', 'Allow Parent Unenrolment', 'Can parents remove student enrolments while the window is open?', 'Y');end
";

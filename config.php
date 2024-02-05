<?php

$Stylesheet              = "index.css";
$javaScript              = "index.js";
$Icon                    = "favicon.svg";
$Logo                    = "logo.svg";
$maxFileSize             = "100";
$sqlDB                   = "csgenDB.sql";
$storeIP                 = true;
$storeAgent              = true;
$storeCreated            = true;
$storeLastUsage          = true;
$publicAccountCreation   = true;
$allowPasswordChange     = true;
$logoHeaderSize          = 24;
$dateFormat              = "Y/m/d";
$timeFormat              = "h:i:sa";
$instanceName            = "csgen";
$instanceDescription     = "This is a csgen instance.";
$footerText              = "Licensed under the GNU Affero General Public License version 3.0.<br><br>Made in Sweden";
$documentLocation        = "documents/";
$attachmentLocation      = "attachments/";
$requestLocation        = "requests/";
$historyLocation         = "history/";

$configFile = "";

if (file_exists("config.ini")) {
    $configFile = "config.ini";
} else if (file_exists("config.def.ini")) {
    $configFile = "config.def.ini";
}

if (!file_exists($configFile)) {
    print "Error: Config file '$configFile' not found.";
    die();
}

// load config file
$configEntries = parse_ini_file($configFile);

$Stylesheet = $configEntries['css'];
$Icon = $configEntries['favicon'];
$Logo = $configEntries['logo'];
$sqlDB = $configEntries['sqldb'];
$storeIP = $configEntries['store_ip'];
$storeAgent = $configEntries['store_user_agent'];
$storeCreated = $configEntries['store_created'];
$storeLastUsage = $configEntries['store_last_usage'];
$logoHeaderSize = $configEntries['logo_header_size'];
$dateFormat = $configEntries['date_format'];
$timeFormat = $configEntries['time_format'];
$instanceName = $configEntries['instance_name'];
$instanceDescription = $configEntries['instance_description'];
$documentLocation = $configEntries['document_location'];
$attachmentLocation = $configEntries['attachment_location'];
$requestLocation = $configEntries['request_location'];
$historyLocation = $configEntries['history_location'];
$footerText = $configEntries['footer_text'];
$allowUsernameChange = $configEntries['allow_change_username'];
$allowPasswordChange = $configEntries['allow_change_password'];
$publicAccountCreation = $configEntries['public_account_create'];
$javaScript = $configEntries['javascript'];
?>

<?php session_start();

include "config.php";
include "core.php";

$Error = "";
$html = "";

if (isset($_REQUEST['e'])) $Error = htmlspecialchars($_REQUEST['e']);
$subdir = isset($_GET['endpoint']) ? $_GET['endpoint'] : '/';

if (!checkIfAdminExists()) {
    header("Location: setup.php");
    die();
}

$html = printHeader($html, 1);
$html = printFooter($html);

print "$html";

?>

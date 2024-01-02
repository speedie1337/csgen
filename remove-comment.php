<?php session_start();

include "core.php";
include "config.php";

$id = -1;

$Redirect = "";
$AuthorizedCreation = 0;

if (isset($_REQUEST['redir'])) {
    $Redirect = htmlspecialchars($_REQUEST['redir']);
}

if (isset($_REQUEST['id'])) {
    $id = $_REQUEST['id'];
} else {
    if ($Redirect == "admin") {
        header("Location: admin.php?e=endpoint&action=comments");
    } else if ($Redirect == "edit") {
        header("Location: edit.php?e=endpoint&action=comments");
    } else {
        header("Location: /");
    }

    die();
}

$Database = createTables($sqlDB);
$DatabaseQuery = $Database->query('SELECT * FROM users');

if (!isset($_SESSION['username']) || !isset($_SESSION['password']) || !isset($_SESSION['type'])) {
    header('Location: login.php?redir=admin');
    die();
} else if ($_SESSION['type'] != 2) { // not allowed
    header('Location: /');
    die();
}

$DatabaseQuery = $Database->query('SELECT * FROM users');
while ($line = $DatabaseQuery->fetchArray()) {
    if ($line['username'] == $_SESSION['username'] && $_SESSION['username'] != "" && $line['password'] == $_SESSION['password'] && $line['usertype'] == 2) {
        $AuthorizedCreation = 1;
        break;
    } else if ($line['username'] == $_SESSION['username'] && $_SESSION['username'] != "" && $line['password'] == $_SESSION['password']) {
        $CommentDatabaseQuery = $Database->query('SELECT * FROM comments');

        while ($cline = $CommentDatabaseQuery->fetchArray()) {
            if ($line['id'] == $id && $line['username'] == $_SESSION['username']) {
                $AuthorizedCreation = 1;
            }
        }

        break;
    }
}

$Username = $_SESSION['username'];

// not authorized
if ($AuthorizedCreation != 1) {
    header('Location: /');
    die();
}

$Database->exec("DELETE FROM comments WHERE id='$id'");

if ($Redirect == "admin") {
    header("Location: admin.php?action=comments");
} else if ($Redirect == "edit") {
    header("Location: edit.php?action=comments");
} else {
    header("Location: /");
}

die();

?>

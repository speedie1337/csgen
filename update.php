<?php session_start();
include "core.php";
include "config.php";

// fields
$Date = "";
$Endpoint = "";
$File = "";
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
        header("Location: admin.php?e=endpoint");
    } else if ($Redirect == "edit") {
        header("Location: edit.php?e=endpoint&action=articles");
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
    }
}

$Username = $_SESSION['username'];

// not authorized
if ($AuthorizedCreation != 1) {
    header('Location: /');
    die();
}

// date must be specified
if (isset($_REQUEST['date']) && (htmlspecialchars($_REQUEST['date']) != "")) {
    $Date = htmlspecialchars($_REQUEST['date']);
} else {
    $Date = date($dateFormat);
}

// endpoint must be specified
if (isset($_REQUEST['endpoint']) && htmlspecialchars($_REQUEST['endpoint']) != "") {
    $Endpoint = htmlspecialchars($_REQUEST['endpoint']);
} else {
    if ($Redirect == "admin") {
        header("Location: admin.php?e=endpoint");
    } else if ($Redirect == "edit") {
        header("Location: edit.php?e=endpoint&action=articles");
    } else {
        header("Location: /");
    }

    die();
}

$DatabaseQuery = $Database->query('SELECT * FROM pages');
while ($line = $DatabaseQuery->fetchArray()) {
    if ($line['id'] == $id && $id != -1) {
        $File = $line['file'];
    }
}

if (isset($_REQUEST['body']) && htmlspecialchars($_REQUEST['body']) != "") {
    $Body = htmlspecialchars($_REQUEST['body']);

    if ($File == '') {
        print("File parameter cannot be empty. $id");
        die();
    }

    if (!file_put_contents($File, $Body)) {
        if ($Redirect == "admin") {
            header("Location: admin.php?e=file");
        } else if ($Redirect == "edit") {
            header("Location: edit.php?e=file&action=articles");
        } else {
            header("Location: /");
        }

        die();
    }
} else {
    if ($Redirect == "admin") {
        header("Location: admin.php?e=file");
    } else if ($Redirect == "edit") {
        header("Location: edit.php?e=file&action=articles");
    } else {
        header("Location: /");
    }

    die();
}

$Database->exec("UPDATE pages SET date='$Date' WHERE id='$id'");
$Database->exec("UPDATE pages SET endpoint='$Endpoint' WHERE id='$id'");

if ($Redirect == "admin") {
    header("Location: admin.php?action=users");
} else if ($Redirect == "edit") {
    header("Location: edit.php?action=articles");
} else {
    header("Location: /");
}

die();

?>

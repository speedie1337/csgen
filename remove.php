<?php session_start();
include "core.php";
include "config.php";

$id = -1;

$History = "false";
$Redirect = "";
$AuthorizedCreation = 0;

if (isset($_REQUEST['redir'])) {
    $Redirect = htmlspecialchars($_REQUEST['redir']);
}

if (isset($_REQUEST['history'])) {
    $History = htmlspecialchars($_REQUEST['history']);
}

if (isset($_REQUEST['id'])) {
    $id = htmlspecialchars($_REQUEST['id']);
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

if ($History == "false") {
    $DatabaseQuery = $Database->query('SELECT * FROM pages');
    while ($line = $DatabaseQuery->fetchArray()) {
        if ($line['id'] == $id && $id != -1) {
            $File = $line['file'];
            $Directory = dirname($File);

            if (is_file($File)) {
                unlink($File);

                if (is_dir($Directory)) {
                    rmdir($Directory);
                }
            }

            $Database->exec("DELETE FROM pages WHERE id='$id'");

            break;
        }
    }
} else {
    $DatabaseQuery = $Database->query('SELECT * FROM history');
    while ($line = $DatabaseQuery->fetchArray()) {
        if ($line['id'] == $id && $id != -1) {
            $File = $line['file'];
            $Directory = dirname($File);

            if (is_file($File)) {
                unlink($File);

                if (is_dir($Directory)) {
                    rmdir($Directory);
                }
            }

            $Database->exec("DELETE FROM history WHERE id='$id'");

            break;
        }
    }
}

if ($Redirect == "admin") {
    header("Location: admin.php?action=users");
} else if ($Redirect == "edit") {
    if ($History == "true") {
        header("Location: edit.php?action=history&id=$id");
    } else {
        header("Location: edit.php?action=articles");
    }
} else {
    header("Location: /");
}

die();

?>

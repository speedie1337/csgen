<?php session_start();
include "config.php";
include "core.php";

$Authorized = 0;
$userType = 0;
$Redirect = "";

if (isset($_REQUEST['redir'])) {
    $Redirect = htmlspecialchars($_REQUEST['redir']);
}

if (isset($_REQUEST['logout']) && htmlspecialchars($_REQUEST['logout']) == "true") {
    session_unset();
    session_destroy();

    header('Location: login.php');
    die();
}

// if a session exists, redirect the user there instead
if (isset($_SESSION['username']) && isset($_SESSION['password'])) {
    if ($Redirect == "index" || ($Redirect == "admin" && htmlspecialchars($_SESSION['type']) != 2) || $Redirect == "") {
        header('Location: /');
        die();
    } else if ($Redirect == "admin") {
        header('Location: admin.php');
        die();
    }
}

if (isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
    $Database = createTables($sqlDB);
    $DatabaseQuery = $Database->query('SELECT * FROM users');
    $Username = "";
    $Password = "";

    while ($line = $DatabaseQuery->fetchArray()) {
        if ($line['username'] == htmlspecialchars($_REQUEST['username']) && htmlspecialchars($_REQUEST['username']) != "" && password_verify(htmlspecialchars($_REQUEST['password']), $line['password'])) {
            $Username = $line['username'];
            $Password = $line['password'];
            $id = $line['id'];

            // update last usage
            if ($storeLastUsage || $storeLastUsage == "true") {
                $lastUsed = date($dateFormat);
                $Database->exec("UPDATE users SET lastused='$lastUsed' WHERE id='$id'");
            }

            // update IP address
            if ($storeIP || $storeIP == "true") {
                $ip = getIPAddress();
                $Database->exec("UPDATE users SET ip='$ip' WHERE id='$id'");
            }

            // update user agent
            if ($storeAgent || $storeAgent == "true") {
                $userAgent = getUserAgent();
                $Database->exec("UPDATE users SET useragent='$userAgent' WHERE id='$id'");
            }

            $Authorized = 1;
            $userType = $line['usertype'];

            break;
        }
    }

    if ($Authorized != 1) {
        if ($Redirect != "") { // just so we can try again and still be redirected to the right place
            header("Location: login.php?e=true&redir=$Redirect");
        } else {
            header("Location: login.php?e=true");
        }
        die();
    }

    $_SESSION['type'] = $userType;
    $_SESSION['username'] = $Username;
    $_SESSION['password'] = $Password;

    if ($Redirect != "") { // just so we can try again and still be redirected to the right place
        header("Location: login.php?e=true&redir=$Redirect");
    } else {
        header("Location: login.php?e=true");
    }

    die();
} else {
    $html = "";

    $html = printHeader($html, 0);

    $html .= "\t\t\t<h1 id='loginHeader'>Login</h1>\n";
    $html .= "\t\t\t\t<p>Enter your username and password to continue.</p>\n";
    $html .= "\t\t\t\t<form action=\"login.php\">\n";
    $html .= "\t\t\t\t\t<input type=\"text\" name=\"username\" placeholder=\"Username\">\n";
    $html .= "\t\t\t\t\t<input type=\"password\" name=\"password\" placeholder=\"Password\">\n";
    if (isset($Redirect)) $html .= "\t\t\t\t\t<input type=\"hidden\" name=\"redir\" value=\"$Redirect\">\n";
    $html .= "\t\t\t\t\t<input type=\"submit\" value=\"Login\">\n";
    $html .= "\t\t\t\t</form>\n";

    if (isset($_REQUEST['e']) && $_REQUEST['e'] == "true") {
        session_unset();
        session_destroy();

        $html .= "\t\t\t\t<p class=\"error\">Invalid username or password.</p>\n";
    }

    $html = printFooter($html);

    print "$html";
}
?>

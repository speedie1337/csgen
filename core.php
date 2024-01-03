<?php

define('BASE', str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));
spl_autoload_register(function($class){ require str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php'; });
use md\MarkdownExtra;
error_reporting(-1);

class parsedMarkdown {
    public $title = '';
    public $description = '';
    public $date = '';
    public $data = '';
    public $allowComments = false;
    public $displayTitle = false;
    public $displayDate = false;
}

function createTables($sqlDB) {
    $Database = new SQLite3($sqlDB);

    /* users table
     * id (INTEGER PRIMARY KEY)
     * username (TEXT)
     * password (TEXT)
     * usertype (INT)
     * primaryadmin (INT)
     * numberofcomments (INT)
     * lastused (TEXT)
     * created (TEXT)
     * ip (TEXT)
     * useragent (TEXT)
     */
    $Database->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, username TEXT, password TEXT, usertype INT, primaryadmin INT, numberofcomments INT, lastused TEXT, created TEXT, ip TEXT, useragent TEXT)");

    /* comments table
     * id (INTEGER PRIMARY KEY)
     * date (TEXT)
     * data (TEXT)
     * username (TEXT)
     * usertype (INT)
     * page (INT)
     */
    $Database->exec("CREATE TABLE IF NOT EXISTS comments(id INTEGER PRIMARY KEY, date TEXT, data TEXT, username TEXT, usertype INT, page INT)");

    /* pages table
     * id (INTEGER PRIMARY KEY)
     * username (TEXT)
     * date (TEXT)
     * endpoint (TEXT)
     * file (TEXT)
     */
    $Database->exec("CREATE TABLE IF NOT EXISTS pages(id INTEGER PRIMARY KEY, username TEXT, date TEXT, endpoint TEXT, file TEXT)");

    return $Database;
}

function removePrefix($prefix, $html) {
    return preg_replace("/$prefix.*/", "", $html);
}

function printCommentField($html, $id, $pageID) {
    include "config.php";

    $html .= "\t\t\t<div id=\"comment_section\" class=\"comment_section\">\n";
    $html .= "\t\t\t\t<h2 id=\"comment_head\" class=\"comment_head\">Comment</h2>\n";

    if (isset($_SESSION['username'])) {
        $html .= "\t\t\t\t\t<p id=\"comment_p\" class=\"comment_p\">Have anything to say? Feel free to comment it below:</p>\n";
        $html .= "\t\t\t\t\t<form class=\"commentWriteForm\" action=\"/comment.php?id=$id&retid=$pageID\" method=\"post\">\n";
        $html .= "\t\t\t\t\t\t<br><textarea id=\"commentWriteArea\" class=\"commentWriteArea\" name=\"body\" rows=\"8\" cols=\"50\"></textarea>\n";
        $html .= "\t\t\t\t\t\t<br><br><input type=\"submit\" value=\"Comment\">\n";
        $html .= "\t\t\t\t\t\t<br><br>\n";
        $html .= "\t\t\t\t\t</form>\n";
    } else {
        $html .= "\t\t\t\t\t<p id=\"comment_p\" class=\"comment_p\">To post a comment, you must be logged in.</p>\n";
    }

    // print the actual list
    $Database = createTables($sqlDB);
    $DatabaseQuery = $Database->query('SELECT * FROM comments');

    while ($line = $DatabaseQuery->fetchArray()) {
        if ($line['page'] == $id) {
            $username = $line['username'];
            $date = $line['date'];
            $data = $line['data'];
            $cid = $line['id'];

            $html .= "\t\t\t\t\t<div class=\"comment\">\n";

            if ($line['usertype'] == 2) {
                $html .= "\t\t\t\t\t\t<p style=\"text-align: left;\"><span class=\"commentAuthorMod\">$username</span> on <span class=\"commentDate\">$date:</span>\n";

                if ($line['username'] == $_SESSION['username'] || $_SESSION['type'] == 2) {
                    $html .= "<a id=\"commentRemove\" href=\"/remove-comment.php?id=$cid&retid=$pageID\">Remove</a></p>\n";
                }

                $html .= "\t\t\t\t\t\t</p>\n";
            } else {
                $html .= "\t\t\t\t\t\t<p style=\"text-align: left;\"><span class=\"commentAuthor\">$username</span> on <span class=\"commentDate\">$date:</span>\n";

                if ($line['username'] == $_SESSION['username'] || $_SESSION['type'] == 2) {
                    $html .= "<a id=\"commentRemove\" href=\"/remove-comment.php?id=$cid&retid=$pageID\">Remove</a></p>\n";
                }

                $html .= "\t\t\t\t\t\t</p>\n";
            }

            $html .= "\t\t\t\t\t\t<p class=\"commentData\">$data</p>\n";
            $html .= "\t\t\t\t\t</div>\n";
        }
    }

    $html .= "\t\t\t</div>\n";

    return $html;
}

function convertMarkdownToHTML($contents) {
    include "config.php";

    $ret = new parsedMarkdown();
    $parser = new MarkdownExtra;
    $parser->no_markup = true;

    $specialSyntax = array(
        '/.*@csgen\.title.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.description.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.date.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.allowComments.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displayTitle.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displayDate.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.span.*&lt;STYLE.*,.*TEXT&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/',
        '/.*@csgen\.span.*&lt;STYLE.*,.*HTML&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/',
        '/.*@csgen\.inline.*&lt;HTML&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.inline.*&lt;CSS&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.inline.*&lt;JAVASCRIPT&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.image.*&lt;SIZE.*,.*PATH&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/',
        '/.*@csgen\.div.*&lt;START.*,.*NAME&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.div.*&lt;END.*,.*NAME&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.div.*&lt;STYLE.*,.*NAME&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/',
        '/.*@csgen\.include.*&lt;HTML&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.include.*&lt;CSS&gt;\(.*&quot;(.*)&quot;\);/',
        '/.*@csgen\.include.*&lt;JAVASCRIPT&gt;\(.*&quot;(.*)&quot;\);/',
    );

    $out = $parser->transform($contents);

    foreach ($specialSyntax as $pattern) {
        $matches = array();

        if (preg_match($pattern, $out, $matches)) {
            switch ($pattern) {
                case '/.*@csgen\.title.*=.*&quot;(.*)(&quot;);/':
                    $ret->title = $matches[1];
                    $out = str_replace($matches[0], '', $out);

                    break;
                case '/.*@csgen\.description.*=.*&quot;(.*)(&quot;);/':
                    $ret->description = $matches[1];
                    $out = str_replace($matches[0], '', $out);

                    break;
                case '/.*@csgen\.date.*=.*&quot;(.*)(&quot;);/':
                    $ret->date = $matches[1];
                    $out = str_replace($matches[0], '', $out);

                    break;
                case '/.*@csgen\.allowComments.*=.*&quot;(.*)(&quot;);/':
                    $ret->allowComments = $matches[1];
                    $out = str_replace($matches[0], '', $out);

                    break;
                case '/.*@csgen\.displayTitle.*=.*&quot;(.*)(&quot;);/':
                    $ret->displayTitle = $matches[1];
                    $out = str_replace($matches[0], '', $out);

                    break;
                case '/.*@csgen\.displayDate.*=.*&quot;(.*)(&quot;);/':
                    $ret->displayDate = $matches[1];
                    $out = str_replace($matches[0], '', $out);

                    break;
                case '/.*@csgen\.span.*&lt;STYLE.*,.*TEXT&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "<span style=\"$matches[1]\">$matches[2]</span>", $out);
                    break;
                case '/.*@csgen\.span.*&lt;STYLE.*,.*HTML&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "<span style=\"$matches[1]\">$matches[2]</span>", $out);
                    break;
                case '/.*@csgen\.div.*&lt;START.*,.*NAME&gt;\(.*&quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "<div class=\"$matches[1]\">", $out);
                    break;
                case '/.*@csgen\.div.*&lt;END.*,.*NAME&gt;\(.*&quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "</div>", $out);
                    break;
                case '/.*@csgen\.div.*&lt;STYLE.*,.*NAME&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "<style>\n.$matches[2] {\n\t$matches[1]\n}\n</style>", $out);
                    break;
                case '/.*@csgen\.inline.*&lt;HTML&gt;\(.*&quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "$matches[1]", $out);
                    break;
                case '/.*@csgen\.inline.*&lt;CSS&gt;\(.*&quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "<style>$matches[1]</style>", $out);
                    break;
                case '/.*@csgen\.inline.*&lt;JAVASCRIPT&gt;\(.*&quot;(.*)&quot;\);/':
                    $out = str_replace($matches[0], "<script>$matches[1]</script>", $out);
                    break;
                case '/.*@csgen\.image.*&lt;SIZE.*,.*PATH&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                    $imgres = array();
                    if (preg_match('/([0-9]*)x([0-9]*)/', $matches[1], $imgres)) {
                        $out = str_replace($matches[0], "<img width=\"$imgres[1]\" height=\"$imgres[2]\" src=\"$matches[2]\">", $out);
                    }

                    break;
                case '/.*@csgen\.include.*&lt;HTML&gt;\(.*&quot;(.*)&quot;\);/':
                    if (file_exists($matches[1])) {
                        $out = str_replace($matches[0], file_get_contents($matches[1]), $out);
                    }

                    break;
                case '/.*@csgen\.include.*&lt;CSS&gt;\(.*&quot;(.*)&quot;\);/':
                    if (file_exists($matches[1])) {
                        $out = str_replace($matches[0], "<link rel=\"stylesheet\" href=\"$matches[1]\">", $out);
                    }

                    break;
                case '/.*@csgen\.include.*&lt;JAVASCRIPT&gt;\(.*&quot;(.*)&quot;\);/':
                    if (file_exists($matches[1])) {
                        $out = str_replace($matches[0], "<script src=\"$matches[1]\"></script>", $out);
                    }

                    break;
            }
        }
    }

    $ret->data = $out;

    return $ret;
}

function printHeader($html, $printpage) {
    include "config.php";

    $id = -1;

    if (isset($_REQUEST['id'])) {
        $id = $_REQUEST['id'];
    }

    $Database = createTables($sqlDB);
    $DatabaseQuery = $Database->query('SELECT * FROM pages');

    $wasFound = 0;
    $i = 0;

    $subdir = isset($_GET['endpoint']) ? $_GET['endpoint'] : '/';
    while ($line = $DatabaseQuery->fetchArray()) {
        $endpoint = $line['endpoint'];
        if ((($endpoint == $subdir || "$endpoint/" == "$subdir") && $id == -1) || ($id != -1 && $i == $id)) {
            $wasFound = 1;
            $ret = convertMarkdownToHTML(file_get_contents($line['file']));

            $title = $instanceName;
            $description = $instanceDescription;

            if ($ret->description != '') {
                $description = $ret->description;
            } else if ($ret->title != '') {
                $title = $ret->title;
            }

            $html .= "<!DOCTYPE html>\n";
            $html .= "<html>\n";
            $html .= "\t<head>\n";
            $html .= "\t\t<meta name=\"description\" content=\"$description\">\n";
            $html .= "\t\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n";

            if (file_exists($Icon)) $html .= "\t\t<link rel=\"icon\" href=\"/$Icon\" />\n";
            if (file_exists($Stylesheet)) $html .= "\t\t<link type=\"text/css\" rel=\"stylesheet\" href=\"/$Stylesheet\"/>\n";
            if (file_exists($javaScript)) $html .= "\t\t<script src=\"/$javaScript\"></script>\n";

            $html .= "\t\t<title>$title</title>\n";
            $html .= "\t\t<div class=\"barTitle\">\n";

            $endpointFound = 0;
            $HeaderDatabaseQuery = $Database->query('SELECT * FROM pages');
            while ($head = $HeaderDatabaseQuery->fetchArray()) {
                if ($head['endpoint'] == "/_head") {
                    $Header = convertMarkdownToHTML(file_get_contents($head['file']));

                    $endpointFound = 1;
                    $html .= "\t\t<small id='title'>$Header->data</small>\n";
                    break;
                }
            }

            if ($endpointFound == 0) {
                if (file_exists($Logo)) $html .= "\t\t\t<img src=\"/$Logo\" id=\"titleLogo\" class=\"title\" width=\"$logoHeaderSize\">\n";

                $html .= "\t\t\t<small id='title'><a id='title' href=\"/\">$title</a></small>\n";
            }

            $html .= "\t\t</div>\n";
            $html .= "\t\t<div class=\"barMenu\">\n";

            $html .= "\t\t\t<script>\n";
            $html .= "\t\t\t\tfunction pelem() {\n";
            $html .= "\t\t\t\t\tdocument.getElementById(\"dropdown\").classList.toggle(\"show\");\n";
            $html .= "\t\t\t\t}\n";
            $html .= "\t\t\t\t\n";
            $html .= "\t\t\t\twindow.onclick = function(event) {\n";
            $html .= "\t\t\t\tif (!event.target.matches('.actionmenu')) {\n";
            $html .= "\t\t\t\t\tvar dropdowns = document.getElementsByClassName(\"dropdown-content\");\n";
            $html .= "\t\t\t\t\tvar i;\n";
            $html .= "\t\t\t\t\tfor (i = 0; i < dropdowns.length; i++) {\n";
            $html .= "\t\t\t\t\t\tvar openDropdown = dropdowns[i];\n";
            $html .= "\t\t\t\t\t\tif (openDropdown.classList.contains('show')) {\n";
            $html .= "\t\t\t\t\t\t\topenDropdown.classList.remove('show');\n";
            $html .= "\t\t\t\t\t\t}\n";
            $html .= "\t\t\t\t\t}\n";
            $html .= "\t\t\t\t}\n";
            $html .= "\t\t\t}\n";
            $html .= "\t\t\t</script>\n";

            $html .= "\t\t\t<button onclick=\"pelem()\" class=\"actionmenu\">☰</button>\n";
            $html .= "\t\t\t<div id=\"dropdown\" class=\"dropdown-content\">\n";

            $ListDatabaseQuery = $Database->query('SELECT * FROM pages');
            while ($list = $ListDatabaseQuery->fetchArray()) {
                if ($list['endpoint'] == "/_list") {
                    $List = convertMarkdownToHTML(file_get_contents($list['file']));

                    $html .= "\t\t\t\t$List->data\n";
                }
            }

            if (isset($_SESSION['type']) && $_SESSION['type'] == 2) {
                $html .= "\t\t\t\t<a id='edit' href=\"/edit.php\">Edit</a>\n";
            }

            if (!isset($_SESSION['type'])) {
                if ($publicAccountCreation) {
                    $html .= "\t\t\t\t<a id='register' href=\"/register.php\">Register</a>\n";
                }

                $html .= "\t\t\t\t<a id='login' href=\"/login.php\">Log in</a>\n";
            } else {
                $Username = $_SESSION['username'];
                $html .= "\t\t\t\t<a id='username' href=\"/account.php\">$Username</a>\n";
                $html .= "\t\t\t\t<a id='logout' href=\"/login.php?logout=true\">Log out</a>\n";
            }

            if (isset($_SESSION['type']) && $_SESSION['type'] == 2) {
                $html .= "\t\t\t\t<a id='administration' href=\"/admin.php\">Administration</a>\n";
            }

            $html .= "\t\t\t</div>\n";

            $html .= "\t\t</div>\n";
            $html .= "\t</head>\n";
            $html .= "\t<body>\n";
            $html .= "\t\t<div class=\"content\">\n";

            if ($printpage == 1) {
                if ($ret->displayTitle == "true" && $ret->title != "") {
                    $html .= "\t\t\t<h1 id=\"header\">$ret->title</h1>\n";
                }
                if ($ret->displayDate == "true" && $ret->date != "") {
                    $html .= "\t\t\t\t<p id=\"date\">$ret->date</h1>\n";
                }

                $html .= "\t\t\t\t$ret->data\n";

                if ($ret->allowComments == "true") {
                    $html = printCommentField($html, $line['id'], $i);
                }
            }

	    break;
        }

        $i++;
    }

    if ($wasFound != 1) {
        $title = $instanceName;
        $description = $instanceDescription;

        $html .= "<!DOCTYPE html>\n";
        $html .= "<html>\n";
        $html .= "\t<head>\n";
        $html .= "\t\t<meta name=\"description\" content=\"$description\">\n";
        $html .= "\t\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n";

        if (file_exists($Icon)) $html .= "\t\t<link rel=\"icon\" href=\"/$Icon\" />\n";
        if (file_exists($Stylesheet)) $html .= "\t\t<link type=\"text/css\" rel=\"stylesheet\" href=\"/$Stylesheet\"/>\n";
        if (file_exists($javaScript)) $html .= "\t\t<script src=\"/$javaScript\"></script>\n";

        $html .= "\t\t<title>$title</title>\n";
        $html .= "\t\t<div class=\"barTitle\">\n";

        $endpointFound = 0;
        $HeaderDatabaseQuery = $Database->query('SELECT * FROM pages');
        while ($head = $HeaderDatabaseQuery->fetchArray()) {
            if ($head['endpoint'] == "/_head") {
                $Header = convertMarkdownToHTML(file_get_contents($head['file']));

                $endpointFound = 1;
                $html .= "\t\t<small id='title'>$Header->data</small>\n";
                break;
            }
        }

        if ($endpointFound == 0) {
            if (file_exists($Logo)) $html .= "\t\t\t<img src=\"/$Logo\" id=\"titleLogo\" class=\"title\" width=\"$logoHeaderSize\">\n";

            $html .= "\t\t\t<small id='title'><a id='title' href=\"/\">$title</a></small>\n";
        }

        $html .= "\t\t</div>\n";
        $html .= "\t\t<div class=\"barMenu\">\n";

        $html .= "\t\t\t<script>\n";
        $html .= "\t\t\t\tfunction pelem() {\n";
        $html .= "\t\t\t\t\tdocument.getElementById(\"dropdown\").classList.toggle(\"show\");\n";
        $html .= "\t\t\t\t}\n";
        $html .= "\t\t\t\t\n";
        $html .= "\t\t\t\twindow.onclick = function(event) {\n";
        $html .= "\t\t\t\tif (!event.target.matches('.actionmenu')) {\n";
        $html .= "\t\t\t\t\tvar dropdowns = document.getElementsByClassName(\"dropdown-content\");\n";
        $html .= "\t\t\t\t\tvar i;\n";
        $html .= "\t\t\t\t\tfor (i = 0; i < dropdowns.length; i++) {\n";
        $html .= "\t\t\t\t\t\tvar openDropdown = dropdowns[i];\n";
        $html .= "\t\t\t\t\t\tif (openDropdown.classList.contains('show')) {\n";
        $html .= "\t\t\t\t\t\t\topenDropdown.classList.remove('show');\n";
        $html .= "\t\t\t\t\t\t}\n";
        $html .= "\t\t\t\t\t}\n";
        $html .= "\t\t\t\t}\n";
        $html .= "\t\t\t}\n";
        $html .= "\t\t\t</script>\n";

        $html .= "\t\t\t<button onclick=\"pelem()\" class=\"actionmenu\">☰</button>\n";
        $html .= "\t\t\t<div id=\"dropdown\" class=\"dropdown-content\">\n";

        $ListDatabaseQuery = $Database->query('SELECT * FROM pages');
        while ($list = $ListDatabaseQuery->fetchArray()) {
            if ($list['endpoint'] == "/_list") {
                $List = convertMarkdownToHTML(file_get_contents($list['file']));

                $html .= "\t\t\t\t$List->data\n";
            }
        }

        if (isset($_SESSION['type']) && $_SESSION['type'] == 2) {
            $html .= "\t\t\t\t<a id='edit' href=\"/edit.php\">Edit</a>\n";
        }

        if (!isset($_SESSION['type'])) {
            if ($publicAccountCreation) {
                $html .= "\t\t\t\t<a id='register' href=\"/register.php\">Register</a>\n";
            }

            $html .= "\t\t\t\t<a id='login' href=\"/login.php\">Log in</a>\n";
        } else {
            $Username = $_SESSION['username'];
            $html .= "\t\t\t\t<a id='username' href=\"/account.php\">$Username</a>\n";
            $html .= "\t\t\t\t<a id='logout' href=\"/login.php?logout=true\">Log out</a>\n";
        }

        if (isset($_SESSION['type']) && $_SESSION['type'] == 2) {
            $html .= "\t\t\t\t<a id='administration' href=\"/admin.php\">Administration</a>\n";
        }

        $html .= "\t\t\t</div>\n";

        $html .= "\t\t</div>\n";
        $html .= "\t</head>\n";
        $html .= "\t<body>\n";
        $html .= "\t\t<div class=\"content\">\n";

        if ($printpage == 1) {
            $ErrDatabaseQuery = $Database->query('SELECT * FROM pages');
            $foundErrorPage = 0;
            while ($err = $ErrDatabaseQuery->fetchArray()) {
                if ($err['endpoint'] == "/_404") {
                    $foundErrorPage = 1;
                    $Err = convertMarkdownToHTML(file_get_contents($err['file']));

                    $html .= "\t\t\t$Err->data\n";
		    break;
                }
            }

            if ($foundErrorPage == 0) {
                $html .= "\t\t\t<h1>404</h1>\n\t\t\t\t<p>404: The page you requested could not be found.</p>\n";
            }
        }
    }

    return $html;
}

function printFooter($html) {
    include "config.php";

    $html .= "\t\t</div>\n";
    $html .= "\t</body>\n";
    $html .= "\t<footer>\n";
    $html .= "\t\t<div class='footer'>\n";

    $Database = createTables($sqlDB);
    $DatabaseQuery = $Database->query('SELECT * FROM pages');

    $wasFound = 0;
    while ($line = $DatabaseQuery->fetchArray()) {
        $endpoint = $line['endpoint'];
        if ($endpoint == "/_foot") {
            $wasFound = 1;
            $ret = convertMarkdownToHTML(file_get_contents($line['file']));
            $html .= "\t\t\t$ret->data\n";
            break;
        }
    }

    if ($wasFound == 0) {
        $html .= "\t\t\t<small class='footerText' id='footerText'>$footerText</p>\n";
    }

    $html .= "\t\t</div>\n";
    $html .= "\t</footer>\n";
    $html .= "</html>\n";

    return "$html";
}

function checkIfAdminExists() {
    include "config.php";

    $adminExists = 0;

    $Database = createTables($sqlDB);
    $DatabaseQuery = $Database->query('SELECT * FROM users');

    if (!is_dir($documentLocation)) mkdir($documentLocation, 0777, true);
    if (!is_dir($attachmentLocation)) mkdir($attachmentLocation, 0777, true);

    $adminExists = 0;
    while ($line = $DatabaseQuery->fetchArray()) {
        if ($line['usertype'] == 2) {
            $adminExists = 1;
            break;
        }
    }

    return $adminExists;
}

function getIPAddress() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'];
}

function generatePassword($pwd) {
    return password_hash($pwd, PASSWORD_DEFAULT);
}

?>

<?php

define('BASE', str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));
spl_autoload_register(function($class){ require str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php'; });
use md\MarkdownExtra;
error_reporting(-1);

class parsedMarkdown {
    public $title = '';
    public $description = '';
    public $favicon = '';
    public $license = '';
    public $date = '';
    public $data = '';
    public $authors = array();
    public $allowComments = false;
    public $displayTitle = false;
    public $displayDate = false;
    public $displaySource = true;
    public $displayAuthors = false;
    public $displayLicense = false;
    public $redirectTo = '';
    public $pages = array();
    public $isFeed = false;
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
     * key (TEXT)
     */
    $Database->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, username TEXT, password TEXT, usertype INT, primaryadmin INT, numberofcomments INT, lastused TEXT, created TEXT, ip TEXT, useragent TEXT, key TEXT)");

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

    /* requests table
     * id (INTEGER PRIMARY KEY)
     * pageid (INT)
     * username (TEXT)
     * date (TEXT)
     * message (TEXT)
     * endpoint (TEXT)
     * file (TEXT)
     */
    $Database->exec("CREATE TABLE IF NOT EXISTS requests(id INTEGER PRIMARY KEY, pageid INT, username TEXT, date TEXT, message TEXT, endpoint TEXT, file TEXT)");

    /* history table
     * id (INTEGER PRIMARY KEY)
     * pageid (INT)
     * username (TEXT)
     * date (TEXT)
     * endpoint (TEXT)
     * file (TEXT)
     */
    $Database->exec("CREATE TABLE IF NOT EXISTS history(id INTEGER PRIMARY KEY, pageid INT, username TEXT, date TEXT, endpoint TEXT, file TEXT)");

    return $Database;
}

function removePrefix($prefix, $html) {
    return preg_replace("/$prefix.*/", "", $html);
}

function printFeed($ret, $subdir) {
    include "config.php";

    $title = $ret->title;
    $desc = $ret->description;
    $pages = $ret->pages;

    $rss = "";
    $rss .= "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
    $rss .= "<channel>\n";
    $rss .= "\t<title>$title</title>\n";
    $rss .= "\t<description>$desc</description>\n";
    $rss .= "\t<atom:link href=\"$subdir\" rel=\"self\" type=\"application/rss+xml\"/>\n";

    $rDatabase = createTables($sqlDB);
    $rDatabaseQuery = $rDatabase->query('SELECT * FROM pages');

    while ($rline = $rDatabaseQuery->fetchArray()) {
        foreach ($pages as $i => $it) {
            if ($rline['endpoint'] == $it) {
                // is our page
                $page = convertMarkdownToHTML(file_get_contents($rline['file']));

                $ptitle = $page->title;
                $pdesc = $page->description;
                $pdata = $page->data;
                $pdate = $page->date;

                if ($pdate != "") {
                    $pdate = date('r', strtotime($pdate));
                } else {
                    $pdate = "0";
                }

                $rss .= "\t<item>\n";
                $rss .= "\t\t<title>$ptitle</title>\n";
                $rss .= "\t\t<link>$it</link>\n";
                $rss .= "\t\t<guid>$it</guid>\n";
                $rss .= "\t\t<pubDate>$pdate</pubDate>\n";
                $rss .= "\t\t<description>\n";
                $rss .= "\t\t\t<![CDATA[\n";
                $rss .= "\t\t\t\t$pdata\n";
                $rss .= "\t\t\t]]>\n";
                $rss .= "\t\t</description>\n";
                $rss .= "\t</item>\n";
            }
        }
    }

    $rss .= "</channel>\n";
    $rss .= "</rss>";

    header('Content-type: application/xml');

    print "$rss";

    die();
}

function printCommentField($html, $id, $pageID) {
    include "config.php";

    $html .= "\t\t\t<div id=\"comment_section\" class=\"comment_section\">\n";
    $html .= "\t\t\t\t<h2 id=\"comment_head\" class=\"comment_head\">Comment</h2>\n";

    if (isset($_SESSION['username'])) {
        $html .= "\t\t\t\t\t<p id=\"comment_p\" class=\"comment_p\">Have anything to say? Feel free to comment it below:</p>\n";
        $html .= "\t\t\t\t\t<form method=\"POST\" class=\"commentWriteForm\" action=\"/comment.php?id=$id&retid=$pageID\" method=\"post\">\n";
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

                if (isset($_SESSION['username']) && isset($_SESSION['type'])) {
                    if ($line['username'] == htmlspecialchars($_SESSION['username']) || htmlspecialchars($_SESSION['type']) == 2) {
                        $html .= "<a id=\"commentRemove\" href=\"/remove-comment.php?id=$cid&retid=$pageID\">Remove</a></p>\n";
                    }
                }

                $html .= "\t\t\t\t\t\t</p>\n";
            } else {
                $html .= "\t\t\t\t\t\t<p style=\"text-align: left;\"><span class=\"commentAuthor\">$username</span> on <span class=\"commentDate\">$date:</span>\n";

                if (isset($_SESSION['username']) && isset($_SESSION['type'])) {
                    if ($line['username'] == htmlspecialchars($_SESSION['username']) || htmlspecialchars($_SESSION['type']) == 2) {
                        $html .= "<a id=\"commentRemove\" href=\"/remove-comment.php?id=$cid&retid=$pageID\">Remove</a></p>\n";
                    }
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
    $parser->no_markup = false;

    $specialSyntax = array(
        '/.*@csgen\.title.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.description.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.favicon.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.license.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.date.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.addAuthor.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.allowComments.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displayTitle.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displayDate.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displaySource.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displayAuthors.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.displayLicense.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.markAsFeed.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.includePage.*=.*&quot;(.*)(&quot;);/',
        '/.*@csgen\.redirectTo.*=.*&quot;(.*)(&quot;);/',
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

    while (preg_match('/.*@csgen.*;/', $out)) {
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
                    case '/.*@csgen\.favicon.*=.*&quot;(.*)(&quot;);/':
                        $ret->favicon = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.license.*=.*&quot;(.*)(&quot;);/':
                        $ret->license = $matches[1];
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
                    case '/.*@csgen\.displaySource.*=.*&quot;(.*)(&quot;);/':
                        $ret->displaySource = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.displayAuthors.*=.*&quot;(.*)(&quot;);/':
                        $ret->displayAuthors = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.displayLicense.*=.*&quot;(.*)(&quot;);/':
                        $ret->displayLicense = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.markAsFeed.*=.*&quot;(.*)(&quot;);/':
                        $ret->isFeed = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.addAuthor.*=.*&quot;(.*)(&quot;);/':
                        $ret->authors[] = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.includePage.*=.*&quot;(.*)(&quot;);/':
                        $ret->pages[] = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.redirectTo.*=.*&quot;(.*)(&quot;);/':
                        $ret->redirectTo = $matches[1];
                        $out = str_replace($matches[0], '', $out);

                        break;
                    case '/.*@csgen\.span.*&lt;STYLE.*,.*TEXT&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                        $cssCode = htmlspecialchars_decode($matches[1]);
                        $out = str_replace($matches[0], "<span style=\"$cssCode\">$matches[2]</span>", $out);
                        break;
                    case '/.*@csgen\.span.*&lt;STYLE.*,.*HTML&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                        $cssCode = htmlspecialchars_decode($matches[1]);
                        $htmlCode = htmlspecialchars_decode($matches[2]);
                        $out = str_replace($matches[0], "<span style=\"$cssCode\">$htmlCode</span>", $out);
                        break;
                    case '/.*@csgen\.div.*&lt;START.*,.*NAME&gt;\(.*&quot;(.*)&quot;\);/':
                        $out = str_replace($matches[0], "<div class=\"$matches[1]\">", $out);
                        break;
                    case '/.*@csgen\.div.*&lt;END.*,.*NAME&gt;\(.*&quot;(.*)&quot;\);/':
                        $out = str_replace($matches[0], "</div>", $out);
                        break;
                    case '/.*@csgen\.div.*&lt;STYLE.*,.*NAME&gt;\(.*&quot;(.*)&quot;.*, &quot;(.*)&quot;\);/':
                        $cssCode = htmlspecialchars_decode($matches[1]);
                        $out = str_replace($matches[0], "<style>\n.$matches[2] {\n\t$cssCode\n}\n</style>\n<div class=\"$matches[2]\">", $out);
                        break;
                    case '/.*@csgen\.inline.*&lt;HTML&gt;\(.*&quot;(.*)&quot;\);/':
                        $htmlCode = htmlspecialchars_decode($matches[1]);
                        $out = str_replace($matches[0], "$htmlCode", $out);
                        break;
                    case '/.*@csgen\.inline.*&lt;CSS&gt;\(.*&quot;(.*)&quot;\);/':
                        $cssCode = htmlspecialchars_decode($matches[1]);
                        $out = str_replace($matches[0], "<style>$cssCode</style>", $out);
                        break;
                    case '/.*@csgen\.inline.*&lt;JAVASCRIPT&gt;\(.*&quot;(.*)&quot;\);/':
                        $javascriptCode = htmlspecialchars_decode($matches[1]);
                        $out = str_replace($matches[0], "<script>$javascriptCode</script>", $out);
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
    }

    $ret->data = $out;

    return $ret;
}

function printHeader($html, $printpage) {
    include "config.php";

    $pid = -1;
    $id = -1;

    if (isset($_REQUEST['id'])) {
        $id = htmlspecialchars($_REQUEST['id']);
    }

    $Database = createTables($sqlDB);
    $DatabaseQuery = $Database->query('SELECT * FROM pages');

    $wasFound = 0;

    $title = $instanceName;
    $description = $instanceDescription;

    $subdir = "";
    if (isset($_GET['endpoint'])) {
        $subdir = $_GET['endpoint'];
    } else if (isset($_SERVER['REQUEST_URI'])) {
        $subdir = '/' . trim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
    } else {
        $subdir = '/';
    }

    while ($line = $DatabaseQuery->fetchArray()) {
        $endpoint = $line['endpoint'];
        if ((($endpoint == $subdir || "$endpoint/" == "$subdir") && $id == -1) || ($id != -1 && $printpage == 1)) {
            $pid = $line['id'];

            if ($pid != $id && $id != -1) {
                $pid = -1;
                continue;
            }

            $wasFound = 1;
            $ret = convertMarkdownToHTML(file_get_contents($line['file']));

            $title = $ret->title;
            $description = $ret->description;
            $favicon = $ret->favicon;

            if ($title === "") {
                $title = $instanceName;
            }

            if ($description === "") {
                $description = $instanceDescription;
            }

            $html .= "<!DOCTYPE html>\n";
            $html .= "<html>\n";
            $html .= "\t<head id=\"header\">\n";
            $html .= "\t\t<meta name=\"description\" content=\"$description\">\n";
            $html .= "\t\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n";

            if ($favicon != "") {
                $html .= "\t\t<link rel=\"icon\" href=\"$favicon\"/>\n";
            } else if (file_exists($Icon)) {
                $html .= "\t\t<link rel=\"icon\" href=\"/$Icon\" />\n";
            }

            if (file_exists($Stylesheet)) $html .= "\t\t<link type=\"text/css\" rel=\"stylesheet\" href=\"/$Stylesheet\"/>\n";
            if (file_exists($javaScript)) $html .= "\t\t<script src=\"/$javaScript\"></script>\n";

            $html .= "\t\t<title>$title</title>\n";
            $html .= "\t\t<div id=\"bar_title\" class=\"bar_title\">\n";

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

                $html .= "\t\t\t<small id='title'><a id='title' href=\"/\">$instanceName</a></small>\n";
            }

            $html .= "\t\t</div>\n";
            $html .= "\t\t<div id=\"bar_menu\" class=\"bar_menu\">\n";

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
                $html .= "\t\t\t\t<a id='edit' href=\"/edit.php?id=$pid\">Edit</a>\n";
            }

            if (!isset($_SESSION['type'])) {
                if ($publicAccountCreation) {
                    $html .= "\t\t\t\t<a id='register' href=\"/register.php\">Register</a>\n";
                }

                $html .= "\t\t\t\t<a id='login' href=\"/login.php\">Log in</a>\n";
            } else {
                $Username = htmlspecialchars($_SESSION['username']);
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
            $html .= "\t\t<div id=\"content\" class=\"content\">\n<br><br>\n";

            if ($printpage == 1) {
                if ($ret->redirectTo != '') {
                    $path = $ret->redirectTo;
                    header("Location: $path");
                    die();
                }

                $License = $ret->license;
                $sourceFile = $line['file'];

                if ($ret->isFeed == "true") {
                    printFeed($ret, $subdir);
                }

                if ($ret->displayTitle == "true" && $ret->title != "") {
                    $html .= "\t\t\t<h1 id=\"header\">$ret->title</h1>\n";
                }
                if ($ret->displayDate == "true" && $ret->date != "") {
                    $html .= "\t\t\t\t<p id=\"date\">$ret->date</h1>\n";
                }

                $html .= "\t\t\t\t$ret->data\n";

                if ($ret->displaySource == "true") {
                    $html .= "\t\t\t\t<a id=\"source\" href=\"/$sourceFile\">Source</a>\n";
                }

                if (isset($_SESSION['type'])) {
                    $html .= "\t\t\t\t<a id=\"modify\" href=\"/edit-page.php?id=$pid\">Request changes</a>\n";
                }

                if ($ret->displayLicense == "true" && $License != '') {
                    $html .= "\t\t\t\tThis page is licensed under the $License license.";
                }

                if ($ret->displayAuthors == "true" && $ret->authors) {
                    $html .= "\t\t\t\t<h2 id=\"authors\">Authors</h2>\n";

                    $html .= "\t\t\t\t<p>";

                    foreach ($ret->authors as $i => $it) {
                        $html .= "$it";

                        if (count($ret->authors) != $i + 1) {
                            $html .= ", ";
                        }
                    }

                    $html .= "\t\t\t\t</p>\n";
                }

                if ($ret->allowComments == "true") {
                    $html = printCommentField($html, $line['id'], $pid);
                }
            }

            break;

        }
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
        $html .= "\t\t<div id=\"bar_title\" class=\"bar_title\">\n";

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

            $html .= "\t\t\t<small id='title'><a id='title' href=\"/\">$instanceName</a></small>\n";
        }

        $html .= "\t\t</div>\n";
        $html .= "\t\t<div id=\"bar_menu\" class=\"bar_menu\">\n";

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
            $Username = htmlspecialchars($_SESSION['username']);
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
        $html .= "\t\t<div id=\"content\" class=\"content\">\n";

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
    $html .= "\t<footer id='footer'>\n";
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
    if (!is_dir($historyLocation)) mkdir($historyLocation, 0777, true);
    if (!is_dir($requestLocation)) mkdir($requestLocation, 0777, true);

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

function truncateText($text, $chars) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $text = $text." ";
    $text = substr($text,0,$chars);
    $text = substr($text,0,strrpos($text,' '));
    $text = $text."...";
    return $text;
}

function generatePassword($pwd) {
    return password_hash($pwd, PASSWORD_DEFAULT);
}

?>

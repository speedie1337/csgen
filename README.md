# CoolSiteGENerator

csgen stands for CoolSiteGEnerator and as the name implies it is a site generator that
generates pages on-the-fly.

## Features

- Articles are written in Markdown, with extra powerful csgen markup
- Very customizable, minimal but modern UI with very little JavaScript
- Account system, including administrators, moderators and registering
- Comments
- GPL licensed (no proprietary software here)

## Dependencies

- php
- sqlite3
- Web server
  - You probably want Apache. It will work with another web server,
  but you'll need to port the .htaccess to your preferred web server.

- On Gentoo, you'll need to enable USE flag `sqlite` for package `dev-lang/php`
in case you're testing locally using `php -S`.

- On Debian, you'll need to install the appropriate Apache
plugin if you want to use Apache.

## Installation

1. Set up a web server with php and sqlite3
2. Point it to `index.php`

When no admin account is set up, you'll be prompted to create one.

## Configuration

See config.def.ini.

## csgen syntax

csgen supports special syntax. This syntax should be entered in the
Markdown document and it can be at any point.

- `@csgen.title = "myTitleHere";`
- `@csgen.description = "myDescriptionHere";`
- `@csgen.date = "myDateHere";`
- `@csgen.displayTitle = true;`
- `@csgen.displayDate = true;`
- `@csgen.displaySource = true;`
- `@csgen.enableComments = true;`
- `@csgen.span<STYLE, TEXT>("color: #0000ff;", "thisIsRedText");`
- `@csgen.span<STYLE, HTML>("color: #0000ff;", "<p>thisIsARedHTMLTag</p>");`
- `@csgen.inline<HTML>("<small>myHtmlHere</small>");`
- `@csgen.inline<CSS>("h1 { color: #0000ff; }");`
- `@csgen.inline<JAVASCRIPT>("alert('Hello world!');");`
- `@csgen.image<SIZE, PATH>("1920x1080", "/attachments/image.png");`
- `@csgen.div<START, NAME>("myFirstDiv");`
- `@csgen.div<END, NAME>("myFirstDiv");`
- `@csgen.div<STYLE, NAME>("text-align: left;", "myFirstDiv");`
- `@csgen.include<HTML>("/attachments/index.html");`
- `@csgen.include<CSS>("/attachments/index.css");`
- `@csgen.include<JAVASCRIPT>("/attachments/index.js");`

There are also special csgen reserved endpoints. These are:

- `/`
  - The root document.
- `/_head`
  - The header text.
- `/_foot`
  - The footer text.
- `/_list`
  - Additional items to prepend to the menu.
- `/_404`
  - The error to display when no page was found for the endpoint.

## License

GNU Affero General Public License version 3.0. See `LICENSE` for details.
The font included is called Noto Sans, and is licensed under the SIL Open
Font License. See `OFL.txt` for copyright details.

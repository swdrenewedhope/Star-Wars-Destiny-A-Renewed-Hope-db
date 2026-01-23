SWD : ARH DB
=======

# Install a local copy (Experimental) - Please report bugs

This guide assumes you know how to use the command-line & git commands.

- Install docker.
- Install docker-compose.
- Clone the repo
- ``cd`` into it
- Run the command ``sudo docker compose up -d --build`` in the project root directory.

After the commands above have been run, the entire dev enviornment will be setup for you.

You can visit the server @ http://localhost:8080 in browser.

An admin user is automatically created with the following credentials:

Username: dev
Password: dev

## Known issues

Sets after Echoes of Destiny (as listed in sets.json) fail to import due to the position/id of a dependency set (Echoes of Destiny 2021) not being imported before-hand. I will fix this at some point, meanwhile you can ignore it or manually change the set positions in sets.json so that Echoes of Destiny 2021 imports before Echoes of Destiny. **Do not** push these changes.

## Translating the site

The string literals of this site are hosted in [Loco](https://localise.biz/swdestinydb). If you want to translate the site, please contact project administrator via mail (webmaster@swdestinydb.com), asking for an invitation to the translation platform providing full name, an email and the language you are willing to translate into.

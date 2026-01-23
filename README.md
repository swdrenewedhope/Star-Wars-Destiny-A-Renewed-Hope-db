SWD : ARH DB
=======

# Install a local copy (Experimental)

This guide assumes you know how to use the command-line & git commands.

- Install docker.
- Install docker-compose.
- Clone the repo
- cd into it

## Known issues

Sets after Echoes of Destiny (as listed in sets.json) fail due to the position/id of a dependency set (Echoes of Destiny 2021) not being imported before-hand.
I will fix this at some point, meanwhile you can ignore it or manually change the set positions in sets.json so that Echoes of Destiny 2021 imports before Echoes of Destiny.

## Setup an admin account

- register
- make sure your account is enabled (or run `php app/console fos:user:activate <username>`)
- run `php app/console fos:user:promote --super <username>`

## Translating the site

The string literals of this site are hosted in [Loco](https://localise.biz/swdestinydb). If you want to translate the site, please contact project administrator via mail (webmaster@swdestinydb.com), asking for an invitation to the translation platform providing full name, an email and the language you are willing to translate into.

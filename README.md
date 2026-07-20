Star Wars Destiny : ARH DB
============================

# Installation 

This guide assumes you know how to use the command-line, git commands and basic docker.

- Install docker & docker-compose.
- Add your user to the docker group.
- Clone this repo and go into it.
- Copy docker-compose.yml.dist -> docker-compose.yml and adjust as needed.
- Run the command ``docker compose up`` in the project root directory.

You can visit the server @ http://localhost in browser.

If SYMFONY_ENV is set to dev, a dev user is automatically created with user & password dev.

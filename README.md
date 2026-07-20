SWD : ARH DB
=======

# Install a local copy

This guide assumes you know how to use the command-line, git commands and basic docker.

- Install docker.
- Install docker-compose.
- Add your user to the docker group.
- Clone the repo
- ``cd`` into it
- Copy docker-compose.yml.dist -> docker-compose.yml and adjust as needed.
- Run the command ``docker compose up`` in the project root directory.

You can visit the server @ http://localhost in browser.

If SYMFONY_ENV is set to dev, a dev user is automatically created with user & password dev

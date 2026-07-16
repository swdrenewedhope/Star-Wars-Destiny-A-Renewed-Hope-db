SWD : ARH DB
=======

# Install a local copy (Experimental)

This guide assumes you know how to use the command-line & git commands.

- Install docker.
- Install docker-compose.
- Add your user to the docker group.
- Clone the repo
- ``cd`` into it
- Copy docker-compose.yml.dist -> docker-compose.yml and adjust as needed.
- Run the command ``docker compose up -d --build`` in the project root directory.

You can visit the server @ http://localhost in browser.

If CREATE_DEV_ADMIN is set to 1, a user is automatically created with the following credentials:

Username: dev
Password: dev

You can reset the containers with:
``docker compose down``
``docker compose build --no-cache``
``docker compose up``

Optionally, you can also clear container data with:
```docker compose down -v```

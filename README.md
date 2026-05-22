SWD : ARH DB
=======

# Install a local copy (Experimental)

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

You can reset the containers with:
``sudo docker compose down -v --remove-orphans``,
``sudo docker compose up --build``

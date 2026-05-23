SWD : ARH DB
=======

# Install a local copy (Experimental)

This guide assumes you know how to use the command-line & git commands.

- Install docker.
- Install docker-compose.
- Add your user to the docker group.
- Clone the repo
- ``cd`` into it
- Run the command ``docker compose up -d --build`` in the project root directory.

After the commands above have been run, the entire dev enviornment will be setup for you.

You can visit the server @ http://localhost:8080 in browser.

An admin user is automatically created with the following credentials:

Username: dev
Password: dev

You can reset the containers with:
``docker compose down -v``
``docker compose up --build``

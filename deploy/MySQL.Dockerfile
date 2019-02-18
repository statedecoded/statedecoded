FROM mariadb:latest

# "When a container is started for the first time, a new database with the specified name
# will be created and initialized with the provided configuration variables. Furthermore,
# it will execute files with extensions .sh, .sql and .sql.gz that are found in
# /docker-entrypoint-initdb.d. Files will be executed in alphabetical order."
#       -- from https://hub.docker.com/_/mariadb/#initializing-a-fresh-instance
ADD database.sql /docker-entrypoint-initdb.d

ENV MYSQL_ROOT_PASSWORD password
ENV MYSQL_DATABASE statedecoded
ENV MYSQL_USER username
ENV MYSQL_PASSWORD password

RUN apt-get update

EXPOSE 3306

CMD ["mysqld"]

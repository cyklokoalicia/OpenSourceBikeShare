FROM ubuntu

RUN apt update && apt install -y apache2 php libapache2-mod-php php-mbstring php-fdomdocument composer && apt-get clean
RUN update-rc.d apache2 disable


EXPOSE 80

COPY docker-entrypoint.sh /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]

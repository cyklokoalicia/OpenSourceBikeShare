FROM ubuntu

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections

RUN apt update && apt install -y apache2 php libapache2-mod-php php-mbstring php-fdomdocument php-mysql composer zip && apt-get clean
RUN update-rc.d apache2 disable
RUN a2enmod rewrite

EXPOSE 80

COPY docker-entrypoint.sh /docker-entrypoint.sh
COPY apache2.conf /etc/apache2/sites-enabled/000-default.conf

ENTRYPOINT ["/docker-entrypoint.sh"]

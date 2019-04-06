# DOCKERFILE for ocr_sidekick
FROM debian:buster
MAINTAINER Trasrik Galdifei <docker@heilig.cc>
ENV DEBIAN_FRONTEND noninteractive

# Setup German locales
RUN apt-get update && apt-get upgrade -y
RUN apt-get install locales -y
RUN echo "de_DE.UTF-8 UTF-8" >> /etc/locale.gen
RUN locale-gen
ENV LANG de_DE.UTF-8
ENV LANGUAGE de_DE.UTF-8
ENV LC_ALL de_DE.UTF-8

# Install some packages
RUN apt-get install wget php7.3-cli tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng tesseract-ocr-fra ocrmypdf poppler-utils python3-pip git unzip -y
RUN cp /usr/share/zoneinfo/Europe/Berlin /etc/localtime
RUN echo "Europe/Berlin" > /etc/timezone

# Install OCR Sidekick
RUN mkdir -p /ocr_sidekick && chmod 777 /ocr_sidekick
WORKDIR /ocr_sidekick
ADD scripts/ocr_sidekick.php ./ocr_sidekick.php
RUN chmod 0777 ocr_sidekick.php

# Install composer
WORKDIR /ocr_sidekick
RUN wget https://getcomposer.org/installer
RUN php installer
RUN rm installer
ADD scripts/composer.json ./composer.json
RUN php composer.phar install

# Install mounted dir source
RUN mkdir -p /ocr_sidekick_source && chmod 777 /ocr_sidekick_source
RUN mkdir -p /ocr_sidekick_source/0_input && chmod 777 /ocr_sidekick_source/0_input
RUN mkdir -p /ocr_sidekick_source/0_output && chmod 777 /ocr_sidekick_source/0_output
RUN mkdir -p /ocr_sidekick_source/0_processed && chmod 777 /ocr_sidekick_source/0_processed
RUN mkdir -p /ocr_sidekick_source/config && chmod 777 /ocr_sidekick_source/config
RUN mkdir -p /ocr_sidekick_source/logs && chmod 777 /ocr_sidekick_source/logs
RUN mkdir -p /ocr_sidekick_source/temp && chmod 777 /ocr_sidekick_source/temp
RUN mkdir -p /ocr_sidekick_source/workdir && chmod 777 /ocr_sidekick_source/workdir
ADD scripts/config.php /ocr_sidekick_source/config/config.php
RUN chmod 0777 /ocr_sidekick_source/config/config.php

# Populate mounted dir
RUN mkdir -p /ocr_sidekick_mount && chmod 777 /ocr_sidekick_mount
RUN cp -Rf /ocr_sidekick_source/* /ocr_sidekick_mount

# Install Startup-Script
WORKDIR /
ADD scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

# Setup exposed directories
VOLUME /ocr_sidekick_mount

ENV DEBIAN_FRONTEND teletype
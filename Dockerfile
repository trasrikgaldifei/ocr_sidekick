# DOCKERFILE for ocr_sidekick
# Date: 2019-09-25

FROM debian:buster-slim
MAINTAINER Trasrik Galdifei <docker@heilig.cc>
ENV DEBIAN_FRONTEND noninteractive

# Install some packages
RUN apt-get update && apt-get install -y --no-install-recommends \
  autoconf \
  automake \
  build-essential \
  git \
  libleptonica-dev \
  libtool \
  locales \
  ocrmypdf \
  php7.3-cli \
  poppler-utils \
  python3-pip \
  python3-venv \
  tesseract-ocr \
  tesseract-ocr-deu \
  tesseract-ocr-eng \
  tesseract-ocr-fra \
  unpaper \
  wget \
  unzip \
  zlib1g-dev

# Setup German locales
RUN echo "de_DE.UTF-8 UTF-8" >> /etc/locale.gen \
  && locale-gen
ENV LANG de_DE.UTF-8
ENV LANGUAGE de_DE.UTF-8
ENV LC_ALL de_DE.UTF-8

# Timezone Setup
RUN cp /usr/share/zoneinfo/Europe/Berlin /etc/localtime \
  && echo "Europe/Berlin" > /etc/timezone

# Compile and install jbig2
RUN mkdir jbig2 \
  && wget --quiet --no-check-certificate https://github.com/agl/jbig2enc/archive/0.29.tar.gz -O - | tar xz -C jbig2 --strip-components=1 \
  && cd jbig2 \
  && ./autogen.sh && ./configure && make && make install \
  && cd .. \
  && rm -rf jbig2

# Install newest ocrmypdf version (PIP venv)
RUN python3 -m venv --system-site-packages /ocrmypdf_env
RUN . /ocrmypdf_env/bin/activate; \
  pip install --upgrade pip \
  && pip install --upgrade ocrmypdf
RUN wget --no-check-certificate https://raw.githubusercontent.com/jbarlow83/OCRmyPDF/master/requirements/test.txt \
  && . /ocrmypdf_env/bin/activate; \
  pip install -r test.txt \
  && rm test.txt;

# Install OCR Sidekick
RUN mkdir -p /ocr_sidekick && chmod 777 /ocr_sidekick
WORKDIR /ocr_sidekick
ADD scripts/ocr_sidekick.class.php ./ocr_sidekick.class.php
ADD scripts/ocr_sidekick_worker.php ./ocr_sidekick_worker.php
RUN chmod 0777 ocr_sidekick.class.php \
  && chmod 0777 ocr_sidekick_worker.php

# Install composer
WORKDIR /ocr_sidekick
ADD scripts/composer.json ./composer.json
RUN wget --no-check-certificate https://getcomposer.org/installer \
  && php installer \
  && rm installer \
  && php composer.phar install

# Remove junk
RUN rm -rf /tmp/* /var/tmp/* /root/* \
  && apt-get remove -y \
     locales \
     git \
     unzip \
     wget \
     autoconf \
     automake \
     libtool \
     build-essential \
  && apt-get autoremove -y \
  && apt-get autoclean -y

# Install mounted dir source
RUN mkdir -p /ocr_sidekick_source \
  && chmod 777 /ocr_sidekick_source \
  && mkdir -p /ocr_sidekick_source/0_input \
  && chmod 777 /ocr_sidekick_source/0_input \
  && mkdir -p /ocr_sidekick_source/0_output \
  && chmod 777 /ocr_sidekick_source/0_output \
  && mkdir -p /ocr_sidekick_source/0_processed \
  && chmod 777 /ocr_sidekick_source/0_processed \
  && mkdir -p /ocr_sidekick_source/config \
  && chmod 777 /ocr_sidekick_source/config \
  && mkdir -p /ocr_sidekick_source/logs \
  && chmod 777 /ocr_sidekick_source/logs \
  && mkdir -p /ocr_sidekick_source/workdir \
  && chmod 777 /ocr_sidekick_source/workdir

# Add config files  
ADD scripts/config.php /ocr_sidekick_source/config/config.php
ADD scripts/tags.php /ocr_sidekick_source/config/tags.php
ADD scripts/tags.php /ocr_sidekick_source/config/sender.php
RUN chmod 0777 /ocr_sidekick_source/config/config.php \
  && chmod 0777 /ocr_sidekick_source/config/tags.php \
  && chmod 0777 /ocr_sidekick_source/config/sender.php
  
# Initially populate mounted dir (in case no directory is mounted)
RUN mkdir -p /ocr_sidekick_mount \
  && chmod 777 /ocr_sidekick_mount \
  && cp -Rf /ocr_sidekick_source/* /ocr_sidekick_mount

# Install Startup-Script
WORKDIR /
ADD scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

# Setup exposed directories
VOLUME /ocr_sidekick_mount

# Setup user
RUN useradd docker \
  && mkdir /home/docker \
  && chown docker:docker /home/docker

USER docker
WORKDIR /home/docker

ENV DEBIAN_FRONTEND teletype
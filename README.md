## OCR Sidekick

# Intention
OCR Sidekick is a private project to automatically process PDF files coming from a scanner.

There are two main reasons for the project:
- The used scanner (Brother ADS-1700W) is not able to automatically apply OCR to a PDF.
- The OCR capability of Paperless (https://github.com/the-paperless-project/paperless) seems not to be sufficient.

Therefore, a solution was needed to unattendedly process the PDF files and pass them on to the Paperless consumer.
This includes:
- rectifying the scan
- applying OCR
- doing some guesswork regarding
  - sender / correspondent
  - document date
  - document title
  - tagging
- Generating a filename to best utilize the Paperless consumer


# The project
This project is mainly based on multiple open source projects:
- OCRmyPDF (https://github.com/jbarlow83/OCRmyPDF)
- unreal4u Telegram API (https://github.com/unreal4u/telegram-api)
- Debian Buster 
- Poppler Utils
- ...

The main focus was on easy and unattended use, which led to a docker image controlled by config files.
Most should be self-explanatory.
README.txt
==========

AI Content Creation Assistant for CKEditor 5 in Drupal 10
---------------------------------------------------------

This module adds a button to Drupal's CKEditor 5, empowering users to generate
content using AI. It integrates seamlessly into the editor and offers minimal,
customizable styling to suit any project's design needs.

Requirements
------------
- Drupal 10.4+ or 11
- CKEditor 5

Features
--------
- Easily accessible AI content generation button within CKEditor 5.
- Minimal yellow styling, easily overridden.

Installation
------------
1. **Install & Enable the Module:**
- Ensure the module is installed and enabled within your Drupal setup.

2. **Configure CKEditor:**
- Go to **Administration > Configuration > Content authoring > Text formats
  and editors** (`admin/config/content/formats`).
- Edit the settings for your desired text format (typically Full HTML).
- Drag and drop the "AI Agent" button into the CKEditor toolbar to make it
  available for content editors.

Development
-----------
1. **Setup Dependencies:**
- Navigate to the project directory.
- Run `npm install` to install all required packages.

2. **Development Mode:**
- Run `npm run watch` to monitor and compile file changes in real-time.

3. **Production Build:**
- Run `npm run build` to compile and optimize for production.

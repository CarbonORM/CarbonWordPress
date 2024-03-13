![PHP Version](https://img.shields.io/packagist/php-v/carbonorm/carbonphp)
![GitHub Release](https://img.shields.io/github/v/release/carbonorm/carbonwordpress)
![Packagist Version](https://img.shields.io/packagist/v/carbonorm/carbonwordpress)
![License](https://img.shields.io/packagist/l/carbonorm/carbonwordpress)
![Size](https://img.shields.io/github/languages/code-size/carbonorm/carbonwordpress)
![Documentation](https://img.shields.io/website?down_color=lightgrey&down_message=Offline&up_color=green&up_message=Online&url=https%3A%2F%2Fcarbonorm.dev)
![Monthly Downloads](https://img.shields.io/packagist/dm/carbonorm/carbonwordpress)
![All Downloads](https://img.shields.io/packagist/dt/carbonorm/carbonwordpress)
![Star](https://img.shields.io/github/stars/carbonorm/carbonwordpress?style=social)

# CarbonWordpress

A WordPress Plugin to provide a GUI for CarbonPHP. **This plugin** is in **active development** and is **not ready for public use**.

## Download Options

This is a general list of different ways to download the CarbonPHP framework. I've ordered them from IMO least technical to
most technical.

1) ~~Search for CarbonPHP in the WordPress plugin store.~~ (Pending review)
2) Download the latest stable code [directly as a zip](https://github.com/CarbonORM/CarbonWordPress/archive/refs/heads/main.zip
   ) from [GitHub](https://github.com/CarbonORM/CarbonWordPress).
   <img style='height: 100%; width: 100%; object-fit: contain' alt="Screenshot 2023-12-21 at 2 47 40 AM" src="https://github.com/CarbonORM/CarbonWordPress/assets/9538357/c169b9c0-ea61-4626-a6d0-be342c21f8fc">
    - Log in to your WordPress account.
    - In the left-side menu, navigate to "Plugins" and click on it.
    - Choose the "Add New" option.
    - Click on the "Upload Plugin" button.
    - Select the "Choose File" button.
    - Locate and pick the plugin's .zip file stored on your local computer.
    - Click "Open."
    - To commence the installation, click on "Install Now."
3) Install the plugin using [Composer](https://getcomposer.org/).
    - Open a terminal and navigate to your WordPress installation.
    - Run the following command to install the plugin.
    ```bash
    composer require carbonorm/carbonwordpress
    ```
    - Log in to your WordPress admin dashboard.
    - In the left-hand menu, click on "Plugins."
    - You will see a list of all your installed plugins. Find the plugin CarbonWordPress and select activate.
4) Clone the repository from GitHub .
    - Open a terminal and navigate into your plugin's directory (typically `wp-content/plugins`)
    - Run the following command to clone the repository.
    ```bash
    git clone git@github.com:CarbonORM/CarbonWordPress.git
    ```
    - Log in to your WordPress admin dashboard.
    - In the left-hand menu, click on "Plugins."
    - You will see a list of all your installed plugins. Find the plugin you want to activate.
    - Under the plugin CarbonWordpress, you should see an "Activate" link. Click on "Activate."
    - The plugin will now be activated, and you should see a confirmation message indicating that the plugin has been
      successfully activated.


# Usage 

In your admin panel, after activation, you will see the CarbonORM tab. This is where all C6 guided user actions will take palce.
<img width="279" alt="Screenshot 2024-03-12 at 1 14 46 AM" src="https://github.com/CarbonORM/CarbonWordPress/assets/9538357/d60c663f-6ed4-4616-b858-a73d04c64439">

## Migrate
## Update Composer
## View WebSocket Logs


```bash
git clone git@github.com:CarbonORM/CarbonWordPress.git
```

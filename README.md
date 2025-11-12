# Linkchecker Plugin

The **Linkchecker** Plugin is an extension for [Grav CMS](https://github.com/getgrav/grav). It checks your site for broken links

## Installation

Installing the Linkchecker plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](https://learn.getgrav.org/cli-console/grav-cli-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install linkchecker

This will install the Linkchecker plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/linkchecker`.

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `linkchecker`. You can find these files on [GitHub](https://github.com/peterswinnen/grav-plugin-linkchecker) or via [GetGrav.org](https://getgrav.org/downloads/plugins).

You should now have all the plugin files under

    /your/site/grav/user/plugins/linkchecker
	
> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/peterswinnen/grav-plugin-linkchecker/blob/main/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/linkchecker/linkchecker.yaml` to `user/config/plugins/linkchecker.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true                  # enables/disables the plugin
debug: false                   # enables/disables plugin debug mode
cron_time: '0 1 * * 6'         #  when to schedule the plugin
only_broken: '1'               # show only broken links in Dashboard pane
check_internal: '1'            # check internal (in-site) links
check_external: '1'            # check external (out of site) links
timeout: '2'                   # how long to wait for an answer when checking links
user_agent: curl/7.74.0        # html user agent to use when checking
email_report: '0'              # enable/disable sending email report of links
include_current_host: 'me.com' # hostname of your site
```

Note that if you use the Admin Plugin, a file with your configuration named linkchecker.yaml will be saved in the `user/config/plugins/`-folder once the configuration is saved in the Admin.

## Usage

1. Configure Linkchecker on /admin/plugins/linkchecker.
2. Enable Broken Links Widget at /admin/plugins/admin
3. Find the Broken Links pane at /admin/dashboard. This pane shows the result of the last Scheduled check. You can click the Re-check Links button to force a recheck.


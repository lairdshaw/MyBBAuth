## About MyBBAuth

MyBBAuth is an extension for recent versions of MediaWiki (>= 1.27.0; latest version tested: 1.38.1) which allows members of a MyBB forum to log in to MediaWiki with their MyBB credentials, and which blocks direct MediaWiki logins and account registrations.

The MyBB forum must be hosted on the same server as the MediaWiki instance, or at least its filesystem and database server must be directly accessible to/by MyBBAuth.

For now, only MyBB forums running on MySQL/MariaDB databases are supported.

## Installation and configuration

Upload the root directory of this plugin as the `MyBBAuth` directory in your MediaWiki's `extensions` directory. Then, in your MediaWiki's `LocalSettings.php` file, add these three lines (making sure the variable assignment of the third isn't overwritten later in the file):

```
wfLoadExtension('MyBBAuth');
$wgMyBBAuthForumPath = '/home/youusername/public_html/mybb/';
$wgGroupPermissions['*']['autocreateaccount'] = true;
```

where you should customise the value of `$wgMyBBAuthForumPath` to the fully qualified path to your MyBB forum's root directory on the shared filesystem. This allows the plugin to access your MyBB configuration file, and thereby connect to its database.

## Credits

MyBBAuth draws major inspiration from, and in a sense is an update for newer versions of MediaWiki to, [MyBB-Mediawiki-Bridge](https://github.com/Modding/MyBB-Mediawiki-Bridge).
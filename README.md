# php-sphinx-admin
Administration tool for Sphinx Search Database
- Build and test queries
- Saves query history
- Includes a Query Builder

# Installation

To install, `composer create-project lawrence72/php-sphinx-admin`

# How to use PHP Sphinx Admin

- By default PHP Sphinx Admin is configured with a basic auth password protection.

- default username: sphinx
- default password: sphinx

*If you continue to use the Basic Auth password protection, please update the user/pass to a more secure one!*

# Configuration

The top of the index.php file contains some config options

- SERVER : Database Hostname/IP
- USERNAME : Database Username (if applicable)
- PASSWORD : Database Password (if applicable)
- PORT : Database Port (usually 9306)

- SHOWSERVER : if set to TRUE, additional Database server options are available, including a `display conf file` option. [Off by default]
-	CONFPATH : Path to sphinx.conf [required if `display conf` option is wanted]

- RESTRICTQUERIES : Restricts queries to SELECT and SHOW type queries. [On by default]
- HISTORY : Number of items in query history.

*Supports PHP 7.4+*

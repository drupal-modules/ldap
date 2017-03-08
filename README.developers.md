# Overview of the LDAP suite

The LDAP suite of modules is modular to allow you to pick and choose the elements your use-case requires. The current
structure is not necessarily ideal but rather keeps with the existing framework to avoid additional migration work.

The architecture in Drupal 8 differs significantly from Drupal 7 and will need to evolve further to become better
testable. The currently present (non-working) integration tests relied on a highly complex configuration and setup
based on SimpleTest. The goal of the current branch is to improve test coverage wherever possible through unit tests and
this testing architecture is being phased out step by step.

## Setting up a development environment

To quickly get up and running without using a production system to query against you can make use of Docker. An example
configuration is provided in the docs directory based on the Harry Potter schools. That script - based on a script by
[Laudanum](https://github.com/Laudanum) - populates a Docker instance with users and groups. A matching server template
can be used to configure Drupal LDAP.

Note that in group configuration you could use businessCategory do derive user groups from attributes but this is
disabled so that group DNs are queried.

## Various LDAP Project Notes

### Case Sensitivity and Character Escaping in LDAP Modules

The class MassageFunctions should be used for dealing with case sensitivity
and character escaping consistently.

The general rule is codified in MassageFunctions which is:
* escape filter values and attribute values when querying ldap
* use unescaped, lower case attribute names when storing attribute names in arrays (as keys or values), databases, or object properties.
* use unescaped, mixed case attribute values when storing attribute values in arrays (as keys or values), databases, or object properties.

So a filter might be built as follows:

```php
$massage = new MassageFunctions;
$username = $massage->massage_text($username, 'attr_value', $massage::$query_ldap)
$objectclass = $massage->massage_text($objectclass, 'attr_value', $massage::$query_ldap)
$filter = "(&(cn=$username)(objectClass=$objectclass))";
```

The following functions are also available:

* escape_dn_value()
* unescape_dn_value()
* unescape_filter_value()
* unescape_filter_value()

### Common variables used in ldap_* and their structures

The structure of $ldap_user and $ldap_entry are different!

#### $ldap_user
@see LdapServer::userUserNameToExistingLdapEntry() return

#### $ldap_entry and $ldap_*_entry.
@see LdapServer::ldap_search() return array

####  $user_attr_key
key of form <attr_type>.<attr_name>[:<instance>] such as field.lname, property.mail, field.aliases:2

## Additional links

* http://www-01.ibm.com/support/docview.wss?uid=swg21240892
* https://cwiki.apache.org/GMOxDOC21/ldap-sample-app-ldap-sample-application.html
* http://trac-hacks.org/wiki/LdapPluginTests
* http://en.wikipedia.org/wiki/Hogwarts
* http://harrypotter.wikia.com/wiki/Hogwarts_School_of_Witchcraft_and_Wizardry

Attributecollector
==================

simplesamlphp auth proc filter, that get attributes from backend database and set to attributes array.

This code is delivered from:
https://forja.rediris.es/svn/confia/attributecollector

Basic configuration
===================

Configure this module as an Auth Proc Filter. More info at
http://rnd.feide.no/content/authentication-processing-filters-simplesamlphp

Example
=======

In the following example the filter is configured for only one hosted IdP
editing the file saml20-idp-hosted

```php
$metadata = array(

		'ssp-idp' => array(

		...

				'authproc' => array(
						10 => array(
								'existing' => 'preserve',
								'class' => 'attributecollector:AttributeCollector',
								'uidfield' => 'subject',
								'collector' => array(
										'class' => 'attributecollector:SQLCollector',
										'dsn' => 'pgsql:host=localhost;dbname=ssp-extra',
										'username' => 'ssp-extra',
										'password' => 'ssp-extra',
										'query' => 'SELECT * from extra where subject=:uidfield',
								)
						)
				),

		...

		)
);
```

Configuration Options explained
===============================

The filter needs the following options:

- class: The filter class. Allways: 'attributecollector:AttributeCollector'
- uidfield: The name of the field used as an unique user identifier. The
            configured collector recives this uid so it can search for extra
            attributes.
- collector: The configuration of the collector used to retrieve the extra
             attributes

The following option is optional:

- existing: Tell the filter what to do when a collected attribute already
            exists in the user attributes. Values can be:
            'preserve': Ignore collected attribute and preserve the old one.
                        This one is the default behaviour.
            'replace': Ignore original attribute and replace it with the
                       collected one.
            'merge': Merge the collected attribute into the array of the
                     original one.

Collector Configuration Options explained
=========================================

The collector configuration array needs at least one option:

- class: The collector class.

Some other options may be needed by the collector, refer to the collector
documentation.

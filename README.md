# YOURLS-Update-30X
Utility to check for endpoint URL redirections and update the long URL in YOURLS

## Features
- Can work on individual keywords, domain based long url's, or the entire database
- Returns number of attempts, successes, failures, and an array of unreachable links
- Indexes the YOURLS_url table when enabled
- API based to avoid PHP script execution limitation (This can be time consuming)

### Installation
-  Copy the `update30X` folder into `YOURLS/user/plugins/`
-  Enable in admin area.

### API
-  `action=u30X`  
If sent alone, this will check the entire table.
-  `keyword=KEYWORD`  
Use this to check a single record. Good for testing purposes.  
-  `domain=EXAMPLE.COM`  
Use this to restrict the check to a single domain.

### Example Use Case:
This plugin was written to manage multiple URL redirects resulting from a Drupal to Wordpress migration.
-  Convert your data to Wordpress and make use of either the recommended [Redirection](https://wordpress.org/plugins/redirection/) or [Simple 301 Redirects](https://wordpress.org/plugins/simple-301-redirects/) plugin. 
-  Retreive redirect data from the Wordpress database and RegEx the daylights out of that dataset so that redirects can be set up in `.htaccess` with something like the following:
```  
RewriteCond %{QUERY_STRING} ^(q=node/1234)$  
RewriteRule ^(.*)$ https://example.com/?p=23 [R=301,L]  
RewriteRule ^/?node/1234w/? https://example.com/?p=23 [R=301,L]  
```  
-  Once the redirects are set up, merely run a command like the following:  
```
$ curl --data "format=json&signature=0YOUR0API0KEY&action=u301&domain=example.com" https://sho.rt/yourls-api.php | python -m json.tool

```  

### Note:
With a YOURLS URL database of roughly 450 links, this process took roughly 3 minutes. Appx. 10 seconds was shaved off of this time with the table index. Please verify that it has been created before attempting large checks.


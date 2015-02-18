echo `date` >> ./app-root/runtime/repo/.openshift/cron/hourly/.log
php -f /var/lib/openshift/54dc4c965973caf18600009c/app-root/runtime/repo/crawler1.php
sleep 3
php -f /var/lib/openshift/54dc4c965973caf18600009c/app-root/runtime/repo/crawler2.php
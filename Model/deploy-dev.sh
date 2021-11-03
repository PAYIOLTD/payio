#!/bin/bash
cd ../../..
ls
echo "create zip"
tar -zcvf PayioLtd.tar.gz PayioLtd
echo "remove previous zip"
ssh  -i ~/.ssh/id_rsa roller@comicsans "sudo rm /var/www/payio-magento/current/pub/app/code/PayioLtd.tar.gz"
echo "remove existing plugin files"
ssh  -i ~/.ssh/id_rsa roller@comicsans "sudo rm -R /var/www/payio-magento/current/pub/app/code/PayioLtd"
echo "move new PayioLtd.tar.gz from local to remote"
scp -i ~/.ssh/id_rsa PayioLtd.tar.gz roller@comicsans:/var/www/payio-magento/current/pub/app/code/
echo "unzip new PayioLtd.tar.gz"
cat PayioLtd.tar.gz | ssh  -i ~/.ssh/id_rsa roller@comicsans "cd /var/www/payio-magento/current/pub/app/code/; tar zxvf -"
echo "remove magento cache and rebuild app"
ssh  -i ~/.ssh/id_rsa roller@comicsans "cd /var/www/payio-magento/current/pub && php bin/magento setup:upgrade && php bin/magento setup:static-content:deploy -f && php bin/magento cache:flush"

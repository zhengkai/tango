## Step 1

run composer, for example:

	cd {DIR}
	composer update

## Step 2

you can link nginx.conf to nginx dir
for example:

    sudo ln -s {DIR}/nginx.conf /etc/nginx/site_enabled/tango.conf

and custom your setting, like `server_name` / `access_log` / `fastcgi_pass` etc
then

	sudo service nginx reload

## Step 3

visit in browser [> demo site](http://localhost/)

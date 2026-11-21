import {copyFileSync,existsSync,readdirSync} from 'node:fs';
for(const file of ['api.php','.htaccess','.user.ini'])copyFileSync(`server/${file}`,`dist/${file}`);
const allowed=new Set(['index.html','api.php','.htaccess','.user.ini','assets']);
for(const file of readdirSync('dist'))if(!allowed.has(file))throw Error(`Unexpected deploy file: ${file}`);
if(existsSync('dist/config.php'))throw Error('Database configuration must never be deployed.');

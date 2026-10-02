const fs=require('fs'); fs.mkdirSync('dist',{recursive:true}); fs.writeFileSync('dist/index.html','<!doctype html><button aria-label="Save">Save</button>');

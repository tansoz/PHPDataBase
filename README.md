# PHPDataBase
A simple database build with PHP

# Usage

  first you have to new the db object
  
  example:
    
    $d = new db("test");
    $d->query("select * from `test` limit 2");

## insert
insert into \`[table name]\` (\`value1\`,\`value2\`,\`value3\`,...)

## create table
create table \`[table name]\`(\`[field name]\` [data type( int / string )]([datalength]),\`[field name]\` [data type( int / string )]([datalength]),...)

## update
update \`[table name]\` set \`[field name]\` = \`value\`,\`[field name]\` = \`value\`,... where \`rows\` = \`[row num]\`

## delete
delete from \`[table name]\` where \`rows\` = \`[row num]\`

## select
select * from \`[table name]\` limit [offset],[length]
or
select * from \`[table name]\` limit [length]

## exist
exist \`[table_name]\`

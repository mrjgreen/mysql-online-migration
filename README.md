# mysql-online-migration

Migrate a database table to a new server with minimal downtime and no loss of data integrity.

When dealing with large database systems, it is sometimes a requirement to move a table from one server to another, with minimal interruption.

This tool allows the bulk of the data to be imported as a csv file without locking the source table. 

Any changes to the table while bulk loading are logged using triggers, which can then be replayed against the new table.

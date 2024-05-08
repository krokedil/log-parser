## Krokedil Log Parser.
This is a simple log parser that will search for a single or multiple terms in all the logs provided and output the rows containing a match to a single file with all the rows.

### How to use:
1. Clone the repository.
2. Copy the logs you want to search in the logs folder.
3. Run the script with the following command:
```bash
## Single term search
composer parse "term1"

## Multiple term search
composer parse "term1" "term2" "term3"
```
4. Read the output file in the output folder that will be named based on the terms you searched for.

If you search for multiple terms, the script will search for each of the terms in the logs and output the rows containing any of the terms. If a row contains multiple terms you search for, a duplicate row will not be printed.
The terms are looked for independently, so if you search for "term1" and "term2", the script will search for "term1" and "term2" in the logs and output the rows containing either "term1" or "term2" or both. They will not be searched for as a single term "term1 term2" so they will not ignore rows that don't contain all terms passed.
The term are also case sensitive, so make sure to match the case of the term you are searching for.

### Examples:
To search for a Klarna order id in the logs simply pass the order id as an argument like this:
```bash
composer parse "7dd857de-ed7f-42dd-b8e7-e455a1cfe3ed"
```

To search for a specific status code in the logs, pass the status code as an argument like this:
```bash
## Add the json key before the status code to avoid matching other numbers in the logs, but use single quotes to wrap the argument to prevent the shell from interpreting the double quotes.
composer parse '"code":400'
```

Windows users also need to escape the double quotes, or the CLI will ignore them and pass the argument without them. This can be done by using the backslash before the double quotes like this:
```bash
composer parse '\"code\":400'
```

You can search for any term you want, but remember that the search is case sensitive, so make sure to match the case of the term you are searching for.
Also if the term is too generic and you have too many logs, the output file can become very large or even cause the script to run out of memory.
In that case, try to narrow down the search term to a more specific one.

### Options:
No options are available as of now.

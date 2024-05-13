## Krokedil Log Parser.
This is a simple log parser that will search for a single or multiple terms in all the logs provided and output the rows containing a match to a single file with all the rows.

### How to use:
1. Clone the repository.
2. Run `composer install` in the root of the project to install the dependencies.
3. Copy the logs you want to search in the logs folder.
4. Run the script with the following command:
```bash
## Single term search
composer parse "term1"

## Multiple term search
composer parse "term1" "term2" "term3"
```
5. Read the output file in the output folder that will be named based on the terms you searched for.

If you search for multiple terms, the script will search for each of the terms in the logs and output the rows containing any of the terms. If a row contains multiple terms you search for, a duplicate row will not be printed.

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

Keep in mind that the output file cant contain special characters in the name, so the filename will be sanitized to only contain letters and numbers to avoid this issue. The output file will be named based on the terms you searched for, so if you search for multiple terms, the filename will contain all the terms separated by a dash. For example if you search for "term1" and "term2", the output file will be named "term1-term2.log".

You can search for any term you want, but remember that the search is case sensitive, so make sure to match the case of the term you are searching for.
Also if the term is too generic and you have too many logs, the output file can become very large or even cause the script to run out of memory.
In that case, try to narrow down the search term to a more specific one.

### Options:
The script has a few options that can be used to customize the search:
- `-l | --logs`: This option will change the folder where the logs are located. By default the `logs` folder in the root of the project is used.
- `-o | --output`: This option will change the output folder for the file. By default the `output` folder is set to the output folder in the root of the project.
- `-v | --verbose`: This option will output the logs that are being searched and the logs that are being written to the output file.
- `-i | --inclusive`: This option will make the search inclusive, meaning that the rows containing all the terms will be output to the file. This is useful if you want to search for multiple terms and only want to output the rows containing all the terms.
- `-h | --help`: This option will output the help text with the options and how to use the script.

### Example with options:
To search for multiple terms and only output the rows containing all the terms, use the inclusive option like this:
```bash
composer parse "term1" "term2" "term3" -- -i
```
Note the `--` before the options, this is needed when passing the options to the script in composer to not have composer interpret the options as arguments their commands.

To enable verbose output, use the verbose option like this:
```bash
composer parse "term1" "term2" "term3" -- -v
```

To enable both verbose output and inclusive search, use both options like this:
```bash
composer parse "term1" "term2" "term3" -- -i -v
```

You can also set both the input and output directory for the logs and the results by using the logs and output options like this:
```bash
composer parse "term1" "term2" "term3" -- -l "path/to/logs" -o "path/to/output"
```
If you don't use these flags, the script will use the default folders for the logs and the output.

### Usage outside of CLI.
This script is primarily built to be used in the CLI, but the class that parses the logs can also be used in any other PHP code if needed.
To use the class in your own code, you can simply include the class and use it like this:
```php
use Krokedil\LogParser\LogParser;

$logs_path = 'path/to/logs';
$output_path = 'path/to/output';
$terms = ['term1', 'term2', 'term3'];
$logParser = new LogParser($logs_path, $output_path, $terms);
$logParser->parse();
```

The class also has two optional parameters that can be used to enable verbose output and inclusive search like this:
```php
$inclusive = true;
$verbose = true;
$logParser = new LogParser( $logs_path, $output_path, $terms, $inclusive, $verbose );
$logParser->parse();
```

# Pepperplate Exporter
A tool for exporting recipes out of the [Pepperplate](http://www.pepperplate.com/) recipe managment system and formatting those recipes in a way that is useful to the user. It will also download and save any images associated with the recipes.

## Requirements

* PHP 7
* BASH terminal

## Installation

- Clone the project

- Run composer 

  ```bash
  composer install -o
  ```

- Create your config file by duplicating ```config/config.sample.yml``` as ```config/config.yml``` and update.

## Usage

```bash
cd path/to/directory
```

Then run it:

```bash
php cli.php import you@email.com your-password
```

To run the importer it requires 3 arguments:

- **Task**: At present there's only 1 option - import
- **Email**: Your Pepperplate email address
- **Password**: Your Pepperplate password

## Config

The config file has 2 properties: the Twig template and the file type to save individual recipes.

### Twig Template

I've only created the pte.twig Twig template, because that's the only one I needed. It's formatted to work with the importer for [Plan to Eat](https://www.plantoeat.com/). 

*If you need another template, please create a pull request and I'd love to add it to the list*

### File Type

The extension of the file type to save the exports. Plan to Eat uses text files, so the default is 'txt'.
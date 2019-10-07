Templator is a tool to generate any type of file (configuration file, terraform
files, salt files, puppet files, anything you want) with input defined in a yaml
file.

Installation
------------

This is a [Symfony][1] project.

Please use the [installation guide from Symfony][2]

In short:
```
$ composer install
```

Templates
---------

Any type of file can be generated.

Place a file in templates/jinja/<directory>/<filename>.<extension>.
This is the template file. Use [twig (jinja)][3] syntax for your template.

Create a file in templates/configs/<directory>/<filename>.yaml .
<directory> and <filename> must be identical to the template.

The first level on the yaml file is `vars` . 

The second level is each twig variable declared in the template.

The third levels are :
* `type`. The variable types. This field is mandatory. Currently supported 
types are :
** int
** string
** select
** password
* `default`. The default value for the variable.
* For `select`type, `values` is mandatory. It contains the possible choices


Example :
```
vars:
  a_string:
    type: string
    default: "default string value"
  an_int:
    type: int
    default: 3.14
  a_choice:
    type: select
    values:
      - yes
      - no
      - maybe
  a_password:
    type: password
    default: ""
```

Images:
Github image provided by [http://tholman.com/github-corners/][4]

[1]: https://symfony.com
[2]: https://symfony.com/doc/current/setup.html#running-symfony-applications
[3]: https://twig.symfony.com/doc/2.x/
[4]: http://tholman.com/github-corners/

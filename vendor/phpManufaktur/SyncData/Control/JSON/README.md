php-json-format
===============

Replacement for PHP's json_encode() that produces nicely formatted JSON output.
<br>Format inspired by www.jsoneditoronline.org

### Usage ###
    $j = new JSONFormat('  ', "\n"); // indent and linebreak characters
    echo $j->format(array("whatever", "more stuff")); // use just like you would json_encode()
The above would output:

    [
      "whatever",
      "more stuff"
    ]

If you want to re-format an existing JSON object, add true as the second parameter to format():

    $j->format('{"first":"John","last":"Doe","age":39,"sex":"M","salary":70000,"registered":true,"interests":["Reading","Mountain Biking","Hacking"],"favorites":{"color":"Blue","sport":"Soccer","food":"Spaghetti"},"skills":[{"category":"Javascript","tests":[{"name":"One","score":90},{"name":"Two","score":96}]},{"category":"CouchDB","tests":[{"name":"One","score":79},{"name":"Two","score":84}]},{"category":"Node.js","tests":[{"name":"One","score":97},{"name":"Two","score":93}]}]}', true);

This would return:

    {
      "first": "John",
      "last": "Doe",
      "age": 39,
      "sex": "M",
      "salary": 70000,
      "registered": true,
      "interests": [
        "Reading",
        "Mountain Biking",
        "Hacking"
      ],
      "favorites": {
        "color": "Blue",
        "sport": "Soccer",
        "food": "Spaghetti"
      },
      "skills": [
        {
          "category": "Javascript",
          "tests": [
            {
              "name": "One",
              "score": 90
            },
            {
              "name": "Two",
              "score": 96
            }
          ]
        },
        {
          "category": "CouchDB",
          "tests": [
            {
              "name": "One",
              "score": 79
            },
            {
              "name": "Two",
              "score": 84
            }
          ]
        },
        {
          "category": "Node.js",
          "tests": [
            {
              "name": "One",
              "score": 97
            },
            {
              "name": "Two",
              "score": 93
            }
          ]
        }
      ]
    }

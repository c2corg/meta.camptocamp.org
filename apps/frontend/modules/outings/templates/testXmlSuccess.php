<html>
<head>
<title>Demo Meta-Engine</title>
  <style>
    html {
        font-family: verdana;
    }
    #metaengine_xml {
        width: 600px;
        height: 600px;
    }
    #metaengine_user_key {
        width: 600px;
        height: 50px;
    }
    </style>
</head>
<body>
<h3>Demo Meta-Engine</h3>

<p>XML test template</p>

<?php echo form_tag('outings/push') ?>
  <?php echo input_tag('metaengine_user_id') ?><br />
  <?php echo textarea_tag('metaengine_user_key') ?><br />
  <?php echo textarea_tag('metaengine_xml', 
'<?xml version="1.0" encoding="UTF-8"?>
<outings xmlns="http://meta.camptocamp.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://meta.camptocamp.org http://meta.camptocamp.org/metaengineschema.xsd">
    <outing>
        <name>test name 1</name>
        <geom>6.072,45.653</geom>
        <elevation>3800</elevation>
        <date>2007-08-06</date>
        <lang>fr</lang>
        <activity>1</activity>
        <activity>5</activity>
        <activity>10</activity>
        <rating>très dur</rating>
        <facing>4</facing>
        <original_outing_id>42</original_outing_id>
        <url>http://example.com/sortie42.html</url>
    </outing>
    <outing>
        <name>test name 2</name>
        <elevation>2452</elevation>
        <date>2007-09-07</date>
        <lang>fr</lang>
        <activity>2</activity>
        <activity>6</activity>
        <activity>10</activity>
        <rating>moyen</rating>
        <facing>5</facing>
        <original_outing_id>43</original_outing_id>
        <url>http://example.com/sortie18.html</url>
        <region_code>8</region_code>
    </outing>
    <outing>
        <name>test name 3</name>
        <elevation>1200</elevation>
        <date>2007-10-08</date>
        <lang>fr</lang>
        <activity>3</activity>
        <activity>7</activity>
        <activity>10</activity>
        <rating>facile</rating>
        <facing>6</facing>
        <original_outing_id>44</original_outing_id>
        <url>http://example.com/sortie31.html</url>
        <region_name>Mont Blanc</region_name>
    </outing>
</outings>
') ?>
  <?php echo submit_tag('Submit') ?>
</form>

</body>
</html>

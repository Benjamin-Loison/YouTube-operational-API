#!/usr/bin/env python

'''
This script purpose is to ease retrieving the JSON path associated to an interested YouTube data entry.
For instance when looking for a feature on YouTube UI let say a YouTube video title that we want to automate the retrieval we plug as `filePath` of this script the returned YouTube UI HTML. This script will extract and update to the provided `filePath` the relevant JSON encoded in the appropriate JavaScript variable. Then this script looks recursively for the entry concerning the specific video title you are looking for.
For instance:

```bash
curl -s 'https://www.youtube.com/watch?v=jNQXAC9IVRw' > jNQXAC9IVRw.html
./getJSONPathFromKey.py jNQXAC9IVRw.html | grep 'Me at the zoo$'
```
```
105 /contents/twoColumnWatchNextResults/results/results/contents/0/videoPrimaryInfoRenderer/title/runs/0/text Me at the zoo
101 /playerOverlays/playerOverlayRenderer/videoDetails/playerOverlayVideoDetailsRenderer/title/simpleText Me at the zoo
156 /engagementPanels/2/engagementPanelSectionListRenderer/content/structuredDescriptionContentRenderer/items/0/videoDescriptionHeaderRenderer/title/runs/0/text Me at the zoo
170 /engagementPanels/2/engagementPanelSectionListRenderer/content/structuredDescriptionContentRenderer/items/3/reelShelfRenderer/items/0/reelItemRenderer/headline/simpleText Me at the zoo
171 /engagementPanels/2/engagementPanelSectionListRenderer/content/structuredDescriptionContentRenderer/items/3/reelShelfRenderer/items/24/reelItemRenderer/headline/simpleText этому видио 17 лет - Me at the zoo
```

The first number is the path length to ease considering the shortest one.

If for some reason you know the entry name but not the path you can provide the optional `entryName` `./getJSONPathFromKey.py jNQXAC9IVRw.html entryName` to get the whole path.

As there are potentially multiple JavaScript variable names you can provide as the third argument the interesting JavaScript variable name.
'''

import sys
import json
from lxml import html

def treatKey(obj, path, key):
    objKey = obj[key]
    objKeyType = type(objKey)
    value = objKey if (not objKeyType is dict and not objKeyType is list) else ''
    # used to be a print
    return (path + '/' + key, value)

def _finditem(obj, key, path = ''):
    objType = type(obj)
    results = []
    if objType is dict:
        keys = obj.keys()
        if key == '':
            for keyTmp in keys:
                results += [treatKey(obj, path, keyTmp)]
        elif key in keys:
            results += [treatKey(obj, path, key)]
        for keyTmp in keys:
            res = _finditem(obj[keyTmp], key, path + '/' + keyTmp)
            if res != []:
                results += res
    elif objType is list:
        objLen = len(obj)
        for objIndex in range(objLen):
            objEl = obj[objIndex]
            res = _finditem(objEl, key, path + '/' + str(objIndex))
            if res != []:
                results += res
    return results
    

filePath = sys.argv[1]
key = sys.argv[2] if len(sys.argv) >= 3 else ''
# `ytVariableName` could be for instance 'ytInitialPlayerResponse'
ytVariableName = sys.argv[3] if len(sys.argv) >= 4 else 'ytInitialData'

# if not found from key could search by value
# that way could find easily shortest path to get the value as sometimes the value is repeated multiple times

with open(filePath) as f:
    try:
        json.load(f)
        isJSON = True
    except:
        isJSON = False

if not isJSON:
    with open(filePath) as f:
        content = f.read()

    # Should use a JavaScript parser instead of proceeding that way.
    # Same comment concerning `getJSONStringFromHTMLScriptPrefix`, note that both parsing methods should be identical.
    tree = html.fromstring(content)
    ytVariableDeclaration = ytVariableName + ' = '
    for script in tree.xpath('//script'):
        scriptContent = script.text_content()
        if ytVariableDeclaration in scriptContent:
            newContent = scriptContent.split(ytVariableDeclaration)[1][:-1]
            break
    with open(filePath, 'w') as f:
        f.write(newContent)

with open(filePath) as f:
    data = json.load(f)

with open(filePath, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii = False, indent = 4)

pathValues = _finditem(data, key)
if pathValues != []:
    longestPath = len(str(max([len(path) for path, _ in pathValues])))
    for path, value in pathValues:
        pathLength = ' ' * (longestPath - len(str(len(path)))) + str(len(path))
        print(pathLength, path, value)


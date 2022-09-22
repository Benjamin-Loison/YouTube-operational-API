import requests, json
import matplotlib.pyplot as plt

def getContentFromURL(url):
    return requests.get(url).text

videoId = 'NIJ5RiMAmNs'
url = f'https://yt.lemnoslife.com/videos?part=mostReplayed&id={videoId}'
content = getContentFromURL(url)
data = json.loads(content)

Y = []
for heatMarker in data['items'][0]['mostReplayed']['heatMarkers']:
    heatMarker = heatMarker['heatMarkerRenderer']
    intensityScoreNormalized = heatMarker['heatMarkerIntensityScoreNormalized']
    Y += [intensityScoreNormalized]

plt.plot(Y)
plt.show()
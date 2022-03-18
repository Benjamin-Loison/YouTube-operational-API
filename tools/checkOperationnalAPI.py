import json, requests

channelId = 'UCt5USYpzzMCYhkirVQGHwKQ'
query = 'test'

def getURL(url):
    return requests.get(url).text

videoIds = []

def getVideos(pageToken = '', callsIndex = 0):
    global videoIds
    url = 'https://yt.lemnoslife.com/'
    #url += "search?part=snippet&channelId=" + channelId + "&order=viewCount"
    url += "search?part=id&q=" + query + "&type=video"
    if pageToken != '':
        url += '&pageToken=' + pageToken
    res = getURL(url)
    data = json.loads(res)
    for item in data['items']:
        #print(item)
        videoId = item['id']['videoId']
        #print(videoId)
        if not videoId in videoIds:
            videoIds += [videoId]
            #print(len(videoIds), videoId)
    print(len(videoIds), callsIndex)
    if 'nextPageToken' in data:
        getVideos(data['nextPageToken'], callsIndex + 1)

getVideos()


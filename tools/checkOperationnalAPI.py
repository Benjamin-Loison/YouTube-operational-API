#!/usr/bin/python3

import requests

query = 'test'

videoIds = []

def getVideos(pageToken = '', callsIndex = 0):
    global videoIds
    url = f'https://yt.lemnoslife.com/search?part=id&q={query}&type=video'
    if pageToken != '':
        url += '&pageToken=' + pageToken
    data = requests.get(url).json()
    for item in data['items']:
        videoId = item['id']['videoId']
        if not videoId in videoIds:
            videoIds += [videoId]
    print(len(videoIds), callsIndex)
    if 'nextPageToken' in data:
        getVideos(data['nextPageToken'], callsIndex + 1)

getVideos()


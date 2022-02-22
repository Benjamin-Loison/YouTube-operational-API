import subprocess, json

def exec(cmd):
    cmd = cmd.replace("'", '"')
    return subprocess.Popen(cmd, stdout=subprocess.PIPE, shell=True).communicate()[0]

channelId = 'UCt5USYpzzMCYhkirVQGHwKQ'

videoIds = []

def getVideos(pageToken = ''):
    global videoIds
    cmd = "curl -s 'https://yt.lemnoslife.com/search?part=snippet&channelId=" + channelId + "&order=viewCount"
    if pageToken != '':
        cmd += '&pageToken=' + pageToken
    cmd += "'"
    res = exec(cmd)
    data = json.loads(res)
    for item in data['items']:
        #print(item)
        videoId = item['id']['videoId']
        #print(videoId)
        if not videoId in videoIds:
            videoIds += [videoId]
            print(len(videoIds), videoId)
    if 'nextPageToken' in data:
        getVideos(data['nextPageToken'])

getVideos()


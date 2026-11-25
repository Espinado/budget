const { app, BrowserWindow } = require('electron')
const path = require('path')

// Живая база сайта: 'https://budget.arguss.lv'. Пусто — база только на компьютере.
const remote = null

function createWindow() {
  const window = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 960,
    minHeight: 640,
    title: 'Family Money',
    autoHideMenuBar: true,
    backgroundColor: '#f3efe6',
    icon: path.join(__dirname, '..', 'build', 'icon.ico'),
  })

  window.maximize()
  if (remote) {
    window.loadURL(remote)
  } else {
    window.loadFile(path.join(__dirname, '..', 'dist', 'index.html'))
  }
}

app.whenReady().then(createWindow)

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit()
  }
})

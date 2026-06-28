const { app, BrowserWindow } = require('electron');

const targetUrl = process.env.PROMA_PAY_URL || 'http://localhost:8000';

function createWindow() {
  const win = new BrowserWindow({
    width: 1280,
    height: 840,
    minWidth: 960,
    minHeight: 640,
    title: 'پرما پرداخت',
    webPreferences: {
      preload: require('path').join(__dirname, 'preload.js'),
      contextIsolation: true
    }
  });
  win.loadURL(targetUrl);
}

app.whenReady().then(createWindow);
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});

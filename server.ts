import express from "express";
import fs from "fs";
import { execSync, spawn, ChildProcess } from "child_process";
import { createProxyMiddleware } from "http-proxy-middleware";

const app = express();
const PORT = 3000;
const PHP_PORT = 8080;

function getPhpPath(): string {
  const candidates = [
    "/usr/bin/php",
    "/usr/local/bin/php",
    "/bin/php"
  ];
  for (const c of candidates) {
    if (fs.existsSync(c)) return c;
  }
  try {
    const which = execSync("which php 2>/dev/null", { encoding: "utf8" }).trim();
    if (which && fs.existsSync(which)) return which;
  } catch {}

  try {
    console.log("PHP binary not found, attempting automatic installation...");
    execSync(
      'DEBIAN_FRONTEND=noninteractive apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" php-cli php-sqlite3',
      { stdio: "inherit" }
    );
    for (const c of candidates) {
      if (fs.existsSync(c)) return c;
    }
  } catch (e) {
    console.error("Automatic PHP installation error:", e);
  }

  return "php";
}

let phpProcess: ChildProcess | null = null;
let isShuttingDown = false;

function startPhpServer() {
  if (isShuttingDown) return;
  const phpBin = getPhpPath();
  console.log(`Starting PHP server using: ${phpBin} on 127.0.0.1:${PHP_PORT}...`);

  const envPath = `/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${process.env.PATH || ""}`;
  phpProcess = spawn(phpBin, ["-S", `127.0.0.1:${PHP_PORT}`, "-t", process.cwd()], {
    stdio: "inherit",
    env: { ...process.env, PATH: envPath },
  });

  phpProcess.on("error", (err) => {
    console.error("Failed to start PHP server:", err);
    if (!isShuttingDown) {
      setTimeout(startPhpServer, 3000);
    }
  });

  phpProcess.on("exit", (code, signal) => {
    if (!isShuttingDown) {
      console.warn(`PHP server exited (code: ${code}, signal: ${signal}). Restarting in 2s...`);
      setTimeout(startPhpServer, 2000);
    }
  });
}

startPhpServer();

process.on("exit", () => {
  isShuttingDown = true;
  if (phpProcess && !phpProcess.killed) phpProcess.kill();
});

process.on("SIGINT", () => {
  isShuttingDown = true;
  if (phpProcess && !phpProcess.killed) phpProcess.kill();
  process.exit();
});

process.on("SIGTERM", () => {
  isShuttingDown = true;
  if (phpProcess && !phpProcess.killed) phpProcess.kill();
  process.exit();
});

// Redirect root and convenient aliases to NiKXor application entry
app.get("/", (req, res) => {
  res.redirect("/nikxor/index.php");
});

app.get("/index.html", (req, res) => {
  res.redirect("/nikxor/index.php");
});

app.get("/login", (req, res) => {
  res.redirect("/nikxor/modules/login.php");
});

app.get(["/admin", "/secret"], (req, res) => {
  res.redirect("/nikxor/admin/secret_panel_786.php");
});

app.set("trust proxy", true);

// Security: Block direct downloads of sqlite, env, sql dumps, and lock files
app.use((req, res, next) => {
  if (/\.(sqlite|sqlite3|db|lock|env|log|sql)$/i.test(req.path)) {
    return res.status(403).send("Forbidden: Direct access to database and config files is restricted.");
  }
  next();
});

// Proxy all requests directly to the PHP server without stripping URL prefix
app.use(
  createProxyMiddleware({
    target: `http://127.0.0.1:${PHP_PORT}`,
    changeOrigin: true,
    xfwd: true,
    ws: false,
    on: {
      proxyRes: (proxyRes, req, res) => {
        const setCookies = proxyRes.headers["set-cookie"];
        if (setCookies) {
          const cookiesArray = Array.isArray(setCookies) ? setCookies : [setCookies];
          proxyRes.headers["set-cookie"] = cookiesArray.map((cookieStr) => {
            const parts = cookieStr.split(";").map((s) => s.trim()).filter(Boolean);
            let hasSameSite = false;
            let hasSecure = false;
            let hasPartitioned = false;
            const cleanParts: string[] = [];

            for (const p of parts) {
              if (/^SameSite=/i.test(p)) {
                if (!hasSameSite) {
                  cleanParts.push("SameSite=None");
                  hasSameSite = true;
                }
              } else if (/^Secure$/i.test(p)) {
                if (!hasSecure) {
                  cleanParts.push("Secure");
                  hasSecure = true;
                }
              } else if (/^Partitioned$/i.test(p)) {
                if (!hasPartitioned) {
                  cleanParts.push("Partitioned");
                  hasPartitioned = true;
                }
              } else {
                cleanParts.push(p);
              }
            }
            if (!hasSameSite) cleanParts.push("SameSite=None");
            if (!hasSecure) cleanParts.push("Secure");
            if (!hasPartitioned) cleanParts.push("Partitioned");
            return cleanParts.join("; ");
          });
        }
      },
      error: (err, req, res: any) => {
        console.error("PHP proxy error:", err.message);
        if (res && !res.headersSent) {
          res.writeHead(503, { "Content-Type": "text/html; charset=utf-8" });
          res.end(`
            <!DOCTYPE html>
            <html lang="fa" dir="rtl">
            <head>
              <meta charset="UTF-8">
              <meta http-equiv="refresh" content="2">
              <title>در حال راه‌اندازی سرور NiKXor...</title>
              <style>
                body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .card { background: #1e293b; border: 1px solid #334155; padding: 32px; border-radius: 12px; text-align: center; max-width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
                .spinner { width: 40px; height: 40px; border: 3px solid rgba(56, 189, 248, 0.2); border-top-color: #38bdf8; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px; }
                @keyframes spin { to { transform: rotate(360deg); } }
              </style>
            </head>
            <body>
              <div class="card">
                <div class="spinner"></div>
                <h3 style="font-size: 18px; margin: 0 0 10px; color: #38bdf8;">در حال آماده‌سازی سامانه NiKXor...</h3>
                <p style="font-size: 13.5px; color: #94a3b8; margin: 0; line-height: 1.6;">سرور در حال راه‌اندازی است. صفحه به صورت خودکار تا ۲ ثانیه دیگر باز خواهد شد.</p>
              </div>
            </body>
            </html>
          `);
        }
      },
    },
  })
);

app.listen(PORT, "0.0.0.0", () => {
  console.log(`NiKXor Server running on http://0.0.0.0:${PORT} (PHP target: ${PHP_PORT})`);
});

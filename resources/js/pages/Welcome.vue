
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onBeforeUnmount, ref } from 'vue'

const showContact = ref(false)
const customerName = ref('')
const customerMessage = ref('')
const sending = ref(false)
const waiting = ref(false)
const toast = ref('')
const toastType = ref<'success' | 'error' | 'warning'>('success')

let toastTimer: ReturnType<typeof setTimeout> | undefined
let cooldownTimer: ReturnType<typeof setTimeout> | undefined

function showToast(message: string, type: 'success' | 'error' | 'warning' = 'success') {
    toast.value = message
    toastType.value = type

    if (toastTimer) clearTimeout(toastTimer)
    toastTimer = setTimeout(() => {
        toast.value = ''
    }, 3500)
}

function openContact() {
    if (waiting.value) {
        showToast('Vui lòng đợi 60 giây trước khi gửi tiếp nhé!', 'warning')
        return
    }

    showContact.value = true
}



async function sendMessage() {
    if (sending.value || waiting.value) return

    const name = customerName.value.trim()
    const message = customerMessage.value.trim()

    if (!name || !message) {
        showToast('Vui lòng nhập đầy đủ tên và nội dung!', 'warning')
        return
    }

    sending.value = true

    try {
        const serverUrl = import.meta.env.VITE_SERVER_URL?.replace(/\/+$/, '')

        if (!serverUrl) {
            throw new Error('Chưa cấu hình địa chỉ máy chủ trong file .env')
        }

        const response = await fetch(`${serverUrl}/send-telegram`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                name,
                message,
            }),
        })

        const data = await response.json()

        if (!response.ok || data.success !== true) {
            throw new Error(data.message || 'Không gửi được yêu cầu.')
        }

        showContact.value = false
        customerName.value = ''
        customerMessage.value = ''
        waiting.value = true

        showToast('Đã gửi yêu cầu thành công! Chủ quán sẽ sớm hỗ trợ bạn.')

        cooldownTimer = setTimeout(() => {
            waiting.value = false
        }, 60000)

    } catch (error) {
        const errorMessage = error instanceof Error
            ? error.message
            : 'Không thể kết nối máy chủ.'

        showToast(errorMessage, 'error')

    } finally {
        sending.value = false
    }
}


function openZalo() {
    const groupId = 'sqirjx555'
    const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent)

    if (isMobile) {
        window.location.href = `zalo://group/${groupId}`
        setTimeout(() => {
            window.location.href = `https://zalo.me/g/${groupId}`
        }, 800)
    } else {
        window.open(`https://zalo.me/g/${groupId}`, '_blank', 'noopener')
    }
}

function openFacebook() {
    const pageId = '100063904053062'
    window.open(`https://www.facebook.com/${pageId}`, '_blank', 'noopener')
}

function openMessenger() {
    const url = 'https://www.messenger.com/cm/AbY2sfpGd__omG_j/?send_source=cm%3Acopy_invite_link'
    window.open(url, '_blank', 'noopener')
}

onBeforeUnmount(() => {
    if (toastTimer) clearTimeout(toastTimer)
    if (cooldownTimer) clearTimeout(cooldownTimer)
})
</script>

<template>
    <Head title="CICI GAMING - Gaming • Chill • Đỉnh cao" />

    <div class="gaming-home min-h-screen overflow-hidden text-white">
        <!-- Background decoration -->
        <div class="background-glow glow-one"></div>
        <div class="background-glow glow-two"></div>

        <!-- Header -->
        <header class="site-header">
            <div class="header-inner">
                <a href="#" class="brand">
                    <span class="brand-mark">C</span>
                    <span>
                        <strong>CICI GAMING</strong>
                        <small>GAMING • CHILL • ĐỈNH CAO</small>
                    </span>
                </a>

                <nav class="desktop-nav">
                     <a href="/dat-mon" class="primary-button order-button">
                        🛒 ĐẶT NƯỚC NGAY <span>→</span>
                    </a>
                    <a href="#combo">Combo VIP</a>
                    <a href="#price">Bảng giá</a>
                    <a href="#rules">Nội quy</a>
                    <a href="#community">Cộng đồng</a>
                </nav>

                <button class="header-contact" @click="openContact">
                    <span>♧</span> Gọi chủ quán
                </button>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="hero-section">
                <div class="hero-content">
                    <div class="hero-badge">
                        <span class="live-dot"></span>
                        WELCOME TO CICI GAMING
                    </div>

                    <h1>
                        GAME HẾT MÌNH
                        <span>CHILL HẾT NẤC</span>
                    </h1>

                    <p class="hero-description">
                        Không gian gaming dành cho bạn.
                        Trang bị tốt, dịch vụ chu đáo và những giờ phút giải trí tuyệt vời.
                    </p>

                    <div class="hero-actions">
                        <a href="/dat-mon" class="primary-button order-button">
                            🛒 ĐẶT NƯỚC NGAY <span>→</span>
                        </a>

                        <a href="#combo" class="secondary-button">
                            Khám phá Combo VIP
                        </a>

                        <a href="#community" class="secondary-button">
                            Tham gia cộng đồng
                        </a>
                    </div>

                    <div class="hero-bottom">
                        <span><i></i> CICI GAMING</span>
                        <span>PLAY HARD. STAY COOL.</span>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="hero-circle circle-outer"></div>
                    <div class="hero-circle circle-inner"></div>
                    <div class="hero-emblem">
                        <div class="emblem-top">CICI</div>
                        <div class="emblem-main">GAMING</div>
                        <div class="emblem-bottom">EST. GAMING EXPERIENCE</div>
                    </div>
                    <div class="floating-tag tag-top">LEVEL UP</div>
                    <div class="floating-tag tag-bottom">GG WP</div>
                </div>
            </section>

            <!-- Combo -->
            <section id="combo" class="content-section">
                <div class="section-heading">
                    <div>
                        <span class="section-kicker">CHOOSE YOUR EXPERIENCE</span>
                        <h2>COMBO <span>VIP</span></h2>
                        <p>Chọn gói phù hợp với thời gian chơi của bạn.</p>
                    </div>
                    <span class="section-number">01 / 04</span>
                </div>

                <div class="combo-grid">
                    <article class="combo-card">
                        <div class="combo-number">01</div>
                        <div class="combo-icon">☀️</div>
                        <span class="combo-label">MORNING</span>
                        <h3>Gói Sáng</h3>
                        <p class="combo-time">07:00 — 11:15</p>
                        <div class="combo-divider"></div>
                        <div class="combo-price">20.000<span>đ</span></div>
                        <p class="combo-note">Khởi động ngày mới</p>
                    </article>

                    <article class="combo-card featured">
                        <div class="featured-label">LỰA CHỌN CỦA BẠN</div>
                        <div class="combo-number">02</div>
                        <div class="combo-icon">⚡</div>
                        <span class="combo-label">DAY PASS</span>
                        <h3>Gói Ngày</h3>
                        <p class="combo-time">07:00 — 17:00</p>
                        <div class="combo-divider"></div>
                        <div class="combo-price">55.000<span>đ</span></div>
                        <p class="combo-note">Chơi thỏa thích cả ngày</p>
                    </article>

                    <article class="combo-card">
                        <div class="combo-number">03</div>
                        <div class="combo-icon">🌙</div>
                        <span class="combo-label">NIGHT PASS</span>
                        <h3>Gói Đêm</h3>
                        <p class="combo-time">22:00 — 07:00</p>
                        <div class="combo-divider"></div>
                        <div class="combo-price">60.000<span>đ</span></div>
                        <p class="combo-note">🎁 Tặng nước ngọt</p>
                    </article>
                </div>
            </section>

            <!-- Community / Discord -->
            <section class="discord-banner">
                <div class="discord-content">
                    <span class="section-kicker">CICI GAMING COMMUNITY</span>
                    <h2>ĐỪNG CHƠI MỘT MÌNH!</h2>
                    <p>
                        Tham gia Discord CICI GAMING để cập nhật thông báo,
                        kết nối bạn bè và không bỏ lỡ những hoạt động mới.
                    </p>
                    <a
                        href="https://discord.gg/tPS5g4zeJE"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="discord-button"
                    >
                        <span>◉</span> JOIN DISCORD <span>↗</span>
                    </a>
                </div>
                <div class="discord-decoration">
                    <div class="discord-orbit orbit-one"></div>
                    <div class="discord-orbit orbit-two"></div>
                    <div class="discord-symbol">D</div>
                </div>
            </section>

            <!-- Price -->
            <section id="price" class="content-section">
                <div class="section-heading">
                    <div>
                        <span class="section-kicker">DRINK MENU</span>
                        <h2>BẢNG GIÁ <span>NƯỚC UỐNG</span></h2>
                        <p>Giá bán được áp dụng tại CICI GAMING.</p>
                    </div>
                    <span class="section-number">02 / 04</span>
                </div>

                <div class="price-panel">
                    <div class="price-notice">
                        <span>ⓘ</span>
                        Giá nước áp dụng từ ngày 01/01/2026.
                    </div>

                    <div class="price-list">
                        <div class="price-item">
                            <div class="drink-icon">🥤</div>
                            <div class="drink-info">
                                <strong>Nước ngọt & nước trái cây</strong>
                                <span>Sting, Warrior Dâu, Twister, Warrior Nho, Coca-Cola, Nutri</span>
                            </div>
                            <strong class="drink-price">12.000đ</strong>
                        </div>

                        <div class="price-item">
                            <div class="drink-icon">⚡</div>
                            <div class="drink-info">
                                <strong>Bò Húc</strong>
                                <span>Nước tăng lực</span>
                            </div>
                            <strong class="drink-price">15.000đ</strong>
                        </div>

                        <div class="price-item">
                            <div class="drink-icon">💧</div>
                            <div class="drink-info">
                                <strong>Nước lọc</strong>
                                <span>Nước uống đóng chai</span>
                            </div>
                            <strong class="drink-price">6.000đ</strong>
                        </div>
                    </div>

                    <div class="price-footer">
                        <span>🎮</span>
                        Cần hỗ trợ đặt món? Hãy liên hệ nhân viên hoặc chủ quán.
                    </div>
                </div>
            </section>

            <!-- Rules -->
            <section id="rules" class="content-section">
                <div class="section-heading">
                    <div>
                        <span class="section-kicker">PLAY FAIR • RESPECT EVERYONE</span>
                        <h2>NỘI QUY <span>CICI GAMING</span></h2>
                        <p>Cùng nhau xây dựng môi trường chơi game văn minh.</p>
                    </div>
                    <span class="section-number">03 / 04</span>
                </div>

                <div class="rules-panel">
                    <div class="rules-highlight">
                        <span>⚠️</span>
                        <div>
                            <strong>KHÔNG TỰ Ý LẤY NƯỚC / OSHI</strong>
                            <p>Tự ý lấy sẽ kiểm tra camera và tính phí gấp đôi.</p>
                        </div>
                    </div>

                    <div class="rules-grid">
                        <div class="rule-item">
                            <span>01</span>
                            <p>Hãy nhấn chuông gọi khi cần hỗ trợ hoặc bấm nút Gọi chủ quán.</p>
                        </div>
                        <div class="rule-item">
                            <span>02</span>
                            <p>Dép trong nhà vệ sinh vui lòng không mang ra ngoài.</p>
                        </div>
                        <div class="rule-item">
                            <span>03</span>
                            <p>Khi ra về vui lòng đi đúng dép của mình.</p>
                        </div>
                        <div class="rule-item">
                            <span>04</span>
                            <p>Không mang chất kích thích vào quán.</p>
                        </div>
                        <div class="rule-item">
                            <span>05</span>
                            <p>Không gây ồn ào, chửi thề hoặc gây gổ.</p>
                        </div>
                        <div class="rule-item">
                            <span>06</span>
                            <p>Giữ gìn máy móc và tài sản của quán.</p>
                        </div>
                        <div class="rule-item">
                            <span>07</span>
                            <p>Không mang thức ăn nặng mùi vào khu vực máy.</p>
                        </div>
                        <div class="rule-item">
                            <span>08</span>
                            <p>Không tự ý đổi máy khi chưa có sự cho phép.</p>
                        </div>
                        <div class="rule-item">
                            <span>09</span>
                            <p>Tuân thủ quy định về độ tuổi và nội dung.</p>
                        </div>
                        <div class="rule-item warning-rule">
                            <span>10</span>
                            <p>Làm hư hỏng tài sản phải bồi thường.</p>
                        </div>
                        <div class="rule-item">
                            <span>11</span>
                            <p>Quán phục vụ đến 23:00.</p>
                        </div>
                        <div class="rule-item">
                            <span>12</span>
                            <p>Khách hàng có thể đặt nước / Oishi qua mục Giao tiếp.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Community QR -->
            <section id="community" class="content-section">
                <div class="section-heading">
                    <div>
                        <span class="section-kicker">STAY CONNECTED</span>
                        <h2>KẾT NỐI <span>CỘNG ĐỒNG</span></h2>
                        <p>Quét mã QR để tham gia các kênh của CICI GAMING.</p>
                    </div>
                    <span class="section-number">04 / 04</span>
                </div>

                <div class="community-grid">
                    <button class="community-card zalo-card" @click="openZalo">
                        <div class="community-title">
                            <span>💬</span>
                            <div>
                                <strong>NHÓM ZALO</strong>
                                <small>CICI GAMING</small>
                            </div>
                        </div>
                        <img src="/zalo.jpg" alt="QR nhóm Zalo CICI GAMING" />
                        <span class="scan-label">NHẤN ĐỂ THAM GIA ↗</span>
                    </button>

                    <button class="community-card facebook-card" @click="openFacebook">
                        <div class="community-title">
                            <span>📘</span>
                            <div>
                                <strong>FANPAGE FACEBOOK</strong>
                                <small>CICI GAMING</small>
                            </div>
                        </div>
                        <img src="/fanpage.png" alt="QR Facebook CICI GAMING" />
                        <span class="scan-label">NHẤN ĐỂ TRUY CẬP ↗</span>
                    </button>

                    <button class="community-card messenger-card" @click="openMessenger">
                        <div class="community-title">
                            <span>✉️</span>
                            <div>
                                <strong>NHÓM MESSENGER</strong>
                                <small>CICI GAMING</small>
                            </div>
                        </div>
                        <img src="/facebook.jpg" alt="QR Messenger CICI GAMING" />
                        <span class="scan-label">NHẤN ĐỂ THAM GIA ↗</span>
                    </button>
                </div>
            </section>

            <!-- Contact CTA -->
            <section class="contact-banner">
                <div>
                    <span class="section-kicker">WE ARE HERE TO HELP</span>
                    <h2>CẦN HỖ TRỢ?</h2>
                    <p>Gửi yêu cầu trực tiếp đến chủ quán. CICI GAMING luôn sẵn sàng hỗ trợ bạn.</p>
                </div>
                <button class="primary-button" :disabled="waiting" @click="openContact">
                    {{ waiting ? 'VUI LÒNG CHỜ...' : '🔔 GỌI CHỦ QUÁN' }}
                </button>
            </section>
        </main>

        <!-- Footer -->
        <footer class="site-footer">
            <div class="footer-brand">
                <span class="brand-mark">C</span>
                <div>
                    <strong>CICI GAMING</strong>
                    <small>GAMING • CHILL • ĐỈNH CAO</small>
                </div>
            </div>
            <p>© 2026 CICI GAMING. All rights reserved.</p>
            <span class="footer-slogan">PLAY HARD. STAY COOL. 🎮</span>
        </footer>

        <!-- Contact modal -->
        <Transition name="modal">
            <div v-if="showContact" class="modal-overlay" @click.self="showContact = false">
                <div class="contact-modal">
                    <button class="modal-close" @click="showContact = false">×</button>
                    <div class="modal-icon">🔔</div>
                    <span class="section-kicker">CICI GAMING SUPPORT</span>
                    <h2>GỬI YÊU CẦU</h2>
                    <p>Nhập tên tài khoản và nội dung cần hỗ trợ.</p>

                    <form @submit.prevent="sendMessage">
                        <label>Tên tài khoản</label>
                        <input
                            v-model="customerName"
                            type="text"
                            maxlength="100"
                            placeholder="Nhập tên tài khoản..."
                            required
                        />

                        <label>Nội dung yêu cầu</label>
                        <textarea
                            v-model="customerMessage"
                            rows="4"
                            maxlength="4000"
                            placeholder="Bạn cần hỗ trợ điều gì?"
                            required
                        ></textarea>

                        <button type="submit" class="primary-button modal-submit" :disabled="sending">
                            {{ sending ? 'ĐANG GỬI...' : 'GỬI YÊU CẦU →' }}
                        </button>
                    </form>

                    <small class="modal-hint">Thông tin sẽ được gửi đến chủ quán.</small>
                </div>
            </div>
        </Transition>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast" class="toast-message" :class="`toast-${toastType}`">
                {{ toast }}
            </div>
        </Transition>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Orbitron:wght@500;600;700;800;900&display=swap');

.gaming-home {
    --red: #ff3038;
    --orange: #ff762e;
    --yellow: #ffc247;
    --muted: #a1a1aa;
    position: relative;
    isolation: isolate;
    background: #090a0d;
    color: #fff;
    font-family: 'Be Vietnam Pro', sans-serif;
}

.gaming-home * {
    box-sizing: border-box;
}

.gaming-home a {
    text-decoration: none;
}

.gaming-home button {
    font: inherit;
}

.background-glow {
    position: absolute;
    z-index: -1;
    width: 450px;
    height: 450px;
    border-radius: 50%;
    filter: blur(140px);
    opacity: .12;
    pointer-events: none;
}

.glow-one {
    top: 250px;
    left: -250px;
    background: #ff2525;
}

.glow-two {
    top: 1400px;
    right: -250px;
    background: #ff8a27;
}

/* Header */
.site-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgba(9, 10, 13, .88);
    border-bottom: 1px solid rgba(255,255,255,.07);
    backdrop-filter: blur(18px);
}

.header-inner {
    max-width: 1320px;
    min-height: 82px;
    padding: 0 28px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

.brand, .footer-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

.brand-mark {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff3038, #ff8a27);
    font-family: Orbitron, sans-serif;
    font-size: 25px;
    font-weight: 900;
    box-shadow: 0 0 25px rgba(255,48,56,.28);
}

.brand strong, .footer-brand strong {
    display: block;
    font-family: Orbitron, sans-serif;
    font-size: 16px;
    letter-spacing: 1px;
}

.brand small, .footer-brand small {
    display: block;
    margin-top: 4px;
    color: #92929b;
    font-size: 9px;
    letter-spacing: 2px;
}

.desktop-nav {
    display: flex;
    align-items: center;
    gap: 32px;
}

.desktop-nav a {
    color: #b7b7c0;
    font-size: 13px;
    font-weight: 600;
    transition: color .2s;
}

.desktop-nav a:hover {
    color: var(--yellow);
}

.header-contact {
    border: 1px solid rgba(255,80,65,.55);
    border-radius: 9px;
    background: rgba(255,48,56,.10);
    padding: 11px 17px;
    color: #ff7777;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
}

.header-contact:hover {
    background: var(--red);
    color: white;
    box-shadow: 0 0 20px rgba(255,48,56,.25);
}

/* Hero */
.hero-section {
    max-width: 1320px;
    min-height: 570px;
    padding: 85px 28px 55px;
    margin: auto;
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    align-items: center;
    gap: 30px;
}

.hero-badge, .section-kicker {
    color: var(--orange);
    font-family: Orbitron, sans-serif;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 30px;
    padding: 10px 15px;
    background: rgba(255,255,255,.035);
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #41e58a;
    box-shadow: 0 0 12px #41e58a;
}

.hero-content h1 {
    margin: 27px 0 20px;
    font-family: Orbitron, sans-serif;
    font-size: clamp(38px, 5.4vw, 72px);
    line-height: 1.17;
    font-weight: 900;
    letter-spacing: -3px;
}

.hero-content h1 span {
    display: block;
    background: linear-gradient(90deg, #ff3038, #ff9a32);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

.hero-description {
    max-width: 510px;
    color: #a4a4ae;
    font-size: 15px;
    line-height: 1.9;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 13px;
    margin-top: 32px;
}

.primary-button, .secondary-button {
    min-height: 47px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 13px;
    padding: 0 22px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .25s;
}

.primary-button {
    border: 0;
    color: white;
    background: linear-gradient(100deg, #ff3038, #ff762e);
    box-shadow: 0 8px 25px rgba(255,48,56,.18);
}

.primary-button:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255,48,56,.35);
}

.primary-button:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.secondary-button {
    border: 1px solid rgba(255,255,255,.15);
    color: #ddd;
    background: rgba(255,255,255,.04);
}

.secondary-button:hover {
    border-color: var(--orange);
    color: var(--yellow);
}

.hero-bottom {
    display: flex;
    gap: 30px;
    margin-top: 65px;
    color: #696974;
    font-family: Orbitron, sans-serif;
    font-size: 9px;
    letter-spacing: 1.5px;
}

.hero-bottom i {
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-right: 7px;
    border-radius: 50%;
    background: #42d98a;
}

.hero-visual {
    min-height: 390px;
    position: relative;
    display: grid;
    place-items: center;
}

.hero-circle {
    position: absolute;
    border: 1px solid rgba(255,95,55,.2);
    border-radius: 50%;
}

.circle-outer {
    width: 360px;
    height: 360px;
    box-shadow: 0 0 90px rgba(255,48,56,.08), inset 0 0 80px rgba(255,48,56,.06);
}

.circle-inner {
    width: 275px;
    height: 275px;
    border-style: dashed;
    animation: orbit 30s linear infinite;
}

.hero-emblem {
    width: 220px;
    height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255,125,55,.8);
    border-radius: 28px;
    transform: rotate(-7deg);
    background: linear-gradient(145deg, #261216, #111116 65%);
    box-shadow: 0 0 50px rgba(255,48,56,.2), inset 0 0 40px rgba(255,48,56,.1);
}

.emblem-top {
    color: var(--yellow);
    font-family: Orbitron, sans-serif;
    font-size: 32px;
    font-weight: 900;
}

.emblem-main {
    color: white;
    font-family: Orbitron, sans-serif;
    font-size: 28px;
    font-weight: 900;
}

.emblem-bottom {
    margin-top: 12px;
    color: #ff8d63;
    font-size: 7px;
    letter-spacing: 2px;
}

.floating-tag {
    position: absolute;
    padding: 10px 15px;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 8px;
    background: #17171d;
    color: var(--yellow);
    font-family: Orbitron, sans-serif;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
    box-shadow: 0 10px 30px rgba(0,0,0,.4);
}

.tag-top {
    top: 45px;
    right: 30px;
    transform: rotate(8deg);
}

.tag-bottom {
    bottom: 50px;
    left: 20px;
    transform: rotate(-8deg);
}

@keyframes orbit {
    to { transform: rotate(360deg); }
}

/* Sections */
.content-section {
    max-width: 1220px;
    padding: 65px 28px;
    margin: auto;
    scroll-margin-top: 85px;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 20px;
    margin-bottom: 30px;
}

.section-heading h2 {
    margin: 10px 0 8px;
    font-family: Orbitron, sans-serif;
    font-size: clamp(23px, 3vw, 34px);
    font-weight: 900;
    letter-spacing: -1px;
}

.section-heading h2 span {
    color: var(--orange);
}

.section-heading p {
    color: #85858f;
    font-size: 13px;
}

.section-number {
    padding-bottom: 5px;
    color: #5b5b65;
    font-family: Orbitron, sans-serif;
    font-size: 11px;
    white-space: nowrap;
}

/* Combo */
.combo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.combo-card {
    position: relative;
    min-height: 320px;
    padding: 30px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 15px;
    background: linear-gradient(145deg, #17171d, #101014);
    transition: .3s;
}

.combo-card:hover {
    transform: translateY(-7px);
    border-color: rgba(255,126,50,.55);
    box-shadow: 0 15px 40px rgba(0,0,0,.3);
}

.combo-card.featured {
    border-color: rgba(255,83,52,.7);
    background: linear-gradient(145deg, #2a1719, #151116);
    box-shadow: 0 0 35px rgba(255,48,56,.09);
}

.featured-label {
    position: absolute;
    top: 0;
    right: 0;
    padding: 9px 14px;
    border-radius: 0 0 0 10px;
    background: linear-gradient(90deg, #ff3038, #ff8a27);
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 1px;
}

.combo-number {
    position: absolute;
    top: 20px;
    right: 25px;
    color: rgba(255,255,255,.07);
    font-family: Orbitron, sans-serif;
    font-size: 45px;
    font-weight: 900;
}

.combo-icon {
    margin-bottom: 20px;
    font-size: 35px;
}

.combo-label {
    color: var(--orange);
    font-family: Orbitron, sans-serif;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 2px;
}

.combo-card h3 {
    margin: 9px 0;
    font-size: 22px;
    font-weight: 800;
}

.combo-time {
    color: #9696a0;
    font-size: 12px;
}

.combo-divider {
    width: 100%;
    height: 1px;
    margin: 25px 0 18px;
    background: rgba(255,255,255,.09);
}

.combo-price {
    color: #48e69a;
    font-family: Orbitron, sans-serif;
    font-size: 34px;
    font-weight: 900;
}

.combo-price span {
    font-size: 17px;
}

.combo-note {
    margin-top: 8px;
    color: #777782;
    font-size: 11px;
}

/* Discord */
.discord-banner {
    position: relative;
    max-width: 1164px;
    min-height: 270px;
    padding: 45px 55px;
    margin: 40px auto;
    overflow: hidden;
    border: 1px solid rgba(255,110,70,.3);
    border-radius: 18px;
    background: linear-gradient(105deg, #281317, #171219 60%, #101014);
}

.discord-content {
    position: relative;
    z-index: 2;
    max-width: 650px;
}

.discord-content h2 {
    margin: 12px 0;
    font-family: Orbitron, sans-serif;
    font-size: clamp(23px, 3vw, 34px);
    font-weight: 900;
}

.discord-content p {
    max-width: 580px;
    color: #aaa;
    font-size: 13px;
    line-height: 1.8;
}

.discord-button {
    display: inline-flex;
    align-items: center;
    gap: 15px;
    padding: 13px 20px;
    margin-top: 20px;
    border-radius: 8px;
    background: #5865f2;
    color: white;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1px;
    transition: .2s;
}

.discord-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(88,101,242,.3);
}

.discord-decoration {
    position: absolute;
    top: 0;
    right: 30px;
    width: 300px;
    height: 100%;
    display: grid;
    place-items: center;
}

.discord-orbit {
    position: absolute;
    border: 1px solid rgba(255,110,70,.18);
    border-radius: 50%;
}

.orbit-one {
    width: 230px;
    height: 230px;
}

.orbit-two {
    width: 175px;
    height: 175px;
    border-style: dashed;
}

.discord-symbol {
    width: 105px;
    height: 105px;
    display: grid;
    place-items: center;
    border-radius: 30px;
    background: linear-gradient(135deg, #ff3038, #ff8a27);
    font-family: Orbitron, sans-serif;
    font-size: 60px;
    font-weight: 900;
    box-shadow: 0 0 45px rgba(255,48,56,.3);
    transform: rotate(-12deg);
}

/* Price */
.price-panel, .rules-panel {
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 15px;
    background: #121217;
}

.price-notice {
    padding: 17px 25px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    color: #ffbd67;
    font-size: 12px;
}

.price-notice span {
    margin-right: 8px;
}

.price-list {
    padding: 5px 25px;
}

.price-item {
    display: flex;
    align-items: center;
    gap: 17px;
    padding: 22px 0;
    border-bottom: 1px solid rgba(255,255,255,.07);
}

.price-item:last-child {
    border-bottom: 0;
}

.drink-icon {
    width: 48px;
    height: 48px;
    display: grid;
    flex-shrink: 0;
    place-items: center;
    border-radius: 12px;
    background: rgba(255,255,255,.055);
    font-size: 22px;
}

.drink-info {
    min-width: 0;
    flex: 1;
}

.drink-info strong {
    display: block;
    font-size: 13px;
}

.drink-info span {
    display: block;
    margin-top: 5px;
    color: #85858f;
    font-size: 11px;
    line-height: 1.6;
}

.drink-price {
    color: #48e69a;
    font-family: Orbitron, sans-serif;
    font-size: 15px;
    white-space: nowrap;
}

.price-footer {
    padding: 16px 25px;
    background: rgba(255,255,255,.025);
    color: #9696a0;
    font-size: 11px;
}

.price-footer span {
    margin-right: 8px;
}

/* Rules */
.rules-panel {
    padding: 25px;
}

.rules-highlight {
    display: flex;
    align-items: center;
    gap: 17px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,48,56,.3);
    border-radius: 10px;
    background: rgba(255,48,56,.07);
}

.rules-highlight > span {
    font-size: 28px;
}

.rules-highlight strong {
    color: #ff6969;
    font-size: 13px;
}

.rules-highlight p {
    margin-top: 6px;
    color: #aaa;
    font-size: 11px;
}

.rules-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0 30px;
}

.rule-item {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    padding: 15px 0;
    border-bottom: 1px solid rgba(255,255,255,.06);
}

.rule-item > span {
    color: var(--orange);
    font-family: Orbitron, sans-serif;
    font-size: 10px;
    font-weight: 800;
}

.rule-item p {
    color: #c4c4cb;
    font-size: 12px;
    line-height: 1.7;
}

.warning-rule p {
    color: #ff7777;
}

/* Community */
.community-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.community-card {
    min-width: 0;
    padding: 22px;
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 14px;
    background: #141419;
    color: white;
    text-align: left;
    cursor: pointer;
    transition: .3s;
}

.community-card:hover {
    transform: translateY(-6px);
    border-color: rgba(255,255,255,.3);
}

.zalo-card:hover { box-shadow: 0 12px 35px rgba(0,255,220,.08); }
.facebook-card:hover { box-shadow: 0 12px 35px rgba(24,119,242,.12); }
.messenger-card:hover { box-shadow: 0 12px 35px rgba(0,132,255,.12); }

.community-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.community-title > span {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(255,255,255,.07);
    font-size: 20px;
}

.community-title strong {
    display: block;
    font-size: 11px;
    font-weight: 900;
}

.community-title small {
    display: block;
    margin-top: 4px;
    color: #85858f;
    font-size: 10px;
}

.community-card img {
    width: 100%;
    max-width: 220px;
    height: 210px;
    display: block;
    object-fit: contain;
    padding: 10px;
    margin: auto;
    border-radius: 10px;
    background: white;
}

.scan-label {
    display: block;
    margin-top: 20px;
    color: #ffb45f;
    font-family: Orbitron, sans-serif;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1px;
    text-align: center;
}

/* Contact banner */
.contact-banner {
    max-width: 1164px;
    padding: 35px 40px;
    margin: 30px auto 80px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 15px;
    background: linear-gradient(100deg, #1d1518, #151519);
}

.contact-banner h2 {
    margin: 8px 0;
    font-family: Orbitron, sans-serif;
    font-size: 25px;
    font-weight: 900;
}

.contact-banner p {
    color: #9999a3;
    font-size: 12px;
    line-height: 1.7;
}

/* Footer */
.site-footer {
    min-height: 100px;
    padding: 25px max(28px, calc((100% - 1220px) / 2));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border-top: 1px solid rgba(255,255,255,.08);
    background: #07080a;
}

.site-footer p, .footer-slogan {
    color: #777782;
    font-size: 10px;
}

.footer-slogan {
    font-family: Orbitron, sans-serif;
    letter-spacing: 1px;
}

/* Modal */
.modal-overlay {
    position: fixed;
    z-index: 100;
    inset: 0;
    padding: 20px;
    display: grid;
    place-items: center;
    background: rgba(0,0,0,.8);
    backdrop-filter: blur(8px);
}

.contact-modal {
    position: relative;
    width: 100%;
    max-width: 440px;
    padding: 35px;
    border: 1px solid rgba(255,100,70,.35);
    border-radius: 17px;
    background: #17171d;
    box-shadow: 0 25px 80px rgba(0,0,0,.5);
}

.modal-close {
    position: absolute;
    top: 13px;
    right: 17px;
    border: 0;
    background: none;
    color: #aaa;
    font-size: 28px;
    cursor: pointer;
}

.modal-icon {
    margin-bottom: 15px;
    font-size: 35px;
}

.contact-modal h2 {
    margin: 8px 0;
    font-family: Orbitron, sans-serif;
    font-size: 23px;
}

.contact-modal > p {
    margin-bottom: 25px;
    color: #999;
    font-size: 12px;
}

.contact-modal label {
    display: block;
    margin: 15px 0 8px;
    color: #ddd;
    font-size: 11px;
    font-weight: 700;
}

.contact-modal input, .contact-modal textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 8px;
    outline: none;
    background: #0d0e12;
    color: white;
    font: inherit;
    font-size: 12px;
}

.contact-modal input:focus, .contact-modal textarea:focus {
    border-color: var(--orange);
}

.contact-modal textarea {
    resize: vertical;
}

.modal-submit {
    width: 100%;
    margin-top: 20px;
}

.modal-hint {
    display: block;
    margin-top: 15px;
    color: #777;
    font-size: 10px;
    text-align: center;
}

/* Toast */
.toast-message {
    position: fixed;
    z-index: 200;
    right: 25px;
    bottom: 25px;
    max-width: min(390px, calc(100vw - 40px));
    padding: 15px 20px;
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 10px;
    background: #202027;
    color: white;
    font-size: 12px;
    box-shadow: 0 15px 40px rgba(0,0,0,.4);
}

.toast-success { border-left: 4px solid #36d98b; }
.toast-error { border-left: 4px solid #ff4545; }
.toast-warning { border-left: 4px solid #ffb13d; }

.modal-enter-active, .modal-leave-active,
.toast-enter-active, .toast-leave-active {
    transition: .2s ease;
}

.modal-enter-from, .modal-leave-to {
    opacity: 0;
}

.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translateY(15px);
}

/* Responsive */
@media (max-width: 900px) {
    .desktop-nav {
        gap: 15px;
    }

    .hero-section {
        grid-template-columns: 1fr .8fr;
    }

    .hero-visual {
        transform: scale(.85);
    }

    .discord-decoration {
        opacity: .45;
        right: -60px;
    }
}

@media (max-width: 700px) {
    .header-inner {
        min-height: 70px;
        padding: 0 17px;
    }

    .desktop-nav {
        display: none;
    }

    .brand-mark {
        width: 38px;
        height: 38px;
        font-size: 21px;
    }

    .brand strong {
        font-size: 13px;
    }

    .header-contact {
        padding: 10px 11px;
        font-size: 10px;
    }

    .hero-section {
        min-height: auto;
        padding: 65px 22px 35px;
        grid-template-columns: 1fr;
    }

    .hero-content h1 {
        font-size: clamp(36px, 10vw, 54px);
        letter-spacing: -2px;
    }

    .hero-description {
        font-size: 13px;
    }

    .hero-visual {
        min-height: 290px;
        transform: scale(.8);
        margin: -10px 0;
    }

    .hero-bottom {
        margin-top: 35px;
        font-size: 8px;
    }

    .content-section {
        padding: 45px 20px;
    }

    .section-heading {
        align-items: flex-start;
    }

    .section-number {
        display: none;
    }

    .combo-grid, .community-grid {
        grid-template-columns: 1fr;
    }

    .combo-card {
        min-height: 285px;
    }

    .discord-banner {
        margin: 20px;
        padding: 32px 25px;
    }

    .discord-decoration {
        opacity: .15;
        right: -110px;
    }

    .rules-panel {
        padding: 17px;
    }

    .rules-grid {
        grid-template-columns: 1fr;
    }

    .community-card {
        padding: 20px;
    }

    .community-card img {
        height: 220px;
    }

    .contact-banner {
        margin: 25px 20px 55px;
        padding: 27px 22px;
        align-items: flex-start;
        flex-direction: column;
    }

    .site-footer {
        padding: 25px 20px;
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 420px) {
    .hero-actions {
        flex-direction: column;
    }

    .hero-actions a {
        width: 100%;
    }

    .price-list {
        padding: 5px 15px;
    }

    .price-item {
        gap: 10px;
    }

    .drink-info span {
        font-size: 10px;
    }

    .drink-price {
        font-size: 12px;
    }

    .contact-modal {
        padding: 28px 22px;
    }
}
</style>

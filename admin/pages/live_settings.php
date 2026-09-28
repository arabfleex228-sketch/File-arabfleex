<?php
// جلب بيانات البث من قاعدة البيانات
$live_query = $conn->query("SELECT * FROM live_tv_settings WHERE id = 1");
$live_data = $live_query->fetch_assoc();

if ($live_data && $live_data['is_active'] == 1):
?>
<!-- بداية قسم البث المباشر - تصميم عرب فليكس -->
<style>
    .live-tv-wrapper {
        margin-bottom: 3rem;
        animation: fadeIn 1s ease-out;
    }
    .live-player-card {
        position: relative;
        background: #000;
        border-radius: 1rem;
        overflow: hidden;
        border: 2px solid var(--border-color);
        transition: border-color 0.3s ease;
    }
    .live-player-card:hover {
        border-color: var(--brand-gold);
    }
    .iframe-container {
        position: relative;
        padding-top: 56.25%; /* 16:9 */
    }
    .iframe-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
    /* طبقة المعلومات (Overlay) */
    .live-ui {
        position: absolute;
        inset: 0;
        pointer-events: none;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 1.5rem;
        z-index: 10;
    }
    .live-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .live-brand {
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(8px);
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid rgba(218,165,32,0.3);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .live-brand img {
        width: 30px;
        height: 30px;
        border-radius: 50%;
    }
    .live-tag {
        background: #ff0000;
        color: #fff;
        font-weight: 900;
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 3px;
        display: flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 0 15px rgba(255,0,0,0.5);
        animation: live-blink 1.5s infinite;
    }
    @keyframes live-blink { 0%, 100% {opacity: 1} 50% {opacity: 0.5} }
    
    .live-bottom {
        background: linear-gradient(to top, rgba(0,0,0,1), transparent);
        margin: -1.5rem;
        padding: 3rem 1.5rem 1.5rem;
    }
    .live-info h3 {
        color: #fff;
        font-size: 1.25rem;
        font-weight: 800;
        margin: 0;
    }
    .live-info p {
        color: var(--brand-gold);
        font-size: 0.85rem;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="live-tv-wrapper">
    <h2 class="section-title"><i class="fa-solid fa-satellite-dish"></i> بث مباشر <span class="golden-text">عرب فليكس</span></h2>
    <div class="live-player-card">
        <div class="iframe-container">
            <iframe src="<?php echo $live_data['iframe_link']; ?>" allow="autoplay; fullscreen" allowfullscreen></iframe>
        </div>
        
        <div class="live-ui">
            <div class="live-top">
                <div class="live-brand">
                    <img src="<?php echo $live_data['logo_url']; ?>" alt="Live">
                    <span class="text-white font-bold text-sm"><?php echo $live_data['channel_name']; ?></span>
                </div>
                <div class="live-tag">
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                    LIVE
                </div>
            </div>
            
            <div class="live-bottom">
                <div class="live-info">
                    <h3><?php echo $live_data['stream_title']; ?></h3>
                    <p><i class="fas fa-circle text-[8px]"></i> <?php echo $live_data['episode_info']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
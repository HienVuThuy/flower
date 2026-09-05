{{--
    NỘI DUNG HƯỚNG DẪN của từng nhu cầu.

    Để trong Blade chứ không trong cơ sở dữ liệu: đây là bài viết có cấu
    trúc (dẫn nhập + các bước + lưu ý), không phải dữ liệu để lọc hay
    đếm. Nhét vào bảng thì admin sửa được cái tên nhưng không sửa được
    cái quan trọng, còn người đọc mã phải mở hai chỗ mới hiểu một trang.

    Mỗi nhu cầu một khối @case, cùng một khuôn: đoạn dẫn nhập rồi danh
    sách các bước. Khuôn giống nhau nên khách đọc trang thứ hai đã biết
    tìm gì ở đâu.
--}}
@switch($intent)

    @case(\App\Enums\ShoppingIntent::Gift)
        <p class="intent-page__lead">
            Tặng hoa khó ở chỗ chọn <strong>đúng người, đúng dịp</strong> — không phải
            ở chỗ chọn bó đắt nhất. Ba câu hỏi dưới đây trả lời xong là chọn được.
        </p>

        <h2 class="intent-page__heading">1. Dịp gì?</h2>
        <ul class="intent-page__list">
            <li><strong>Sinh nhật</strong> — hoa tươi sáng màu: hướng dương, tulip, hồng pastel.</li>
            <li><strong>Kỷ niệm, tỏ tình</strong> — hồng đỏ là quy ước ai cũng hiểu; hồng pastel nhẹ nhàng hơn nếu chưa chắc về tình cảm đối phương.</li>
            <li><strong>Cảm ơn, chúc mừng</strong> — giỏ hoặc hộp hoa: đứng vững, không cần bình cắm, người nhận không phải loay hoay.</li>
            <li><strong>Thăm người ốm</strong> — tránh hoa thơm nồng; cây chậu nhỏ bền hơn hẳn hoa cắt.</li>
        </ul>

        <h2 class="intent-page__heading">2. Người nhận sẽ để hoa ở đâu?</h2>
        <p class="intent-page__text">
            Đây là câu hay bị quên nhất. Bó hoa to đẹp nhưng người nhận ở ký túc xá
            hoặc đang đi làm xa thì không có bình để cắm, và hoa hỏng trong một ngày.
            Trường hợp đó nên chọn <strong>hộp hoa</strong> (có sẵn xốp giữ ẩm) hoặc
            <strong>cây chậu nhỏ</strong>.
        </p>

        <h2 class="intent-page__heading">3. Giao lúc nào?</h2>
        <ul class="intent-page__list">
            <li>Hoa tươi nên giao <strong>đúng ngày</strong>, không giao sớm — hoa đẹp nhất trong 24 giờ đầu.</li>
            <li>Dịp cao điểm (14/2, 8/3, 20/10) nên đặt trước <strong>2–3 ngày</strong>: không phải vì cửa hàng bận, mà vì nguồn hoa những ngày đó khan và giá lên.</li>
            <li>Muốn kèm thiệp thì ghi lời nhắn vào ô <em>Ghi chú</em> ở bước thanh toán.</li>
        </ul>
        @break

    @case(\App\Enums\ShoppingIntent::Decor)
        <p class="intent-page__lead">
            Cây trang trí trong nhà chết vì <strong>đặt sai chỗ</strong> nhiều hơn là vì
            quên tưới. Chọn theo góc nhà trước, chọn theo dáng cây sau.
        </p>

        <h2 class="intent-page__heading">1. Nhìn ánh sáng ở góc bạn định đặt</h2>
        <ul class="intent-page__list">
            <li><strong>Sát cửa sổ</strong> — sáng gián tiếp cả ngày. Gần như cây nào cũng sống được; lan hồ điệp và sen đá thích nhất chỗ này.</li>
            <li><strong>Giữa phòng khách</strong> — sáng vừa. Monstera, kim ngân, trầu bà.</li>
            <li><strong>Hành lang, góc tối</strong> — gần như không có nắng. Chỉ vài loài chịu được: lưỡi hổ, vạn niên thanh, trầu bà.</li>
            <li><strong>Phòng ngủ</strong> — chọn cây không mùi mạnh, lá không rụng nhiều.</li>
        </ul>

        <h2 class="intent-page__heading">2. Đo chỗ trước khi mua</h2>
        <p class="intent-page__text">
            Cây trong ảnh luôn trông nhỏ hơn ngoài đời. Bàn làm việc cần cây dưới 30cm;
            góc phòng khách thì cây dưới 1m mới cân đối với ghế sofa.
        </p>

        <h2 class="intent-page__heading">3. Ba thứ cần mua cùng</h2>
        <ul class="intent-page__list">
            <li><strong>Đĩa lót chậu</strong> — nước thừa chảy ra sàn gỗ là hỏng sàn, không phải hỏng cây.</li>
            <li><strong>Đất trồng</strong> — để thay đất sau 6–12 tháng, khi đất cũ đã chai.</li>
            <li><strong>Bình tưới vòi dài</strong> — tưới vào gốc mà không ướt lá; lá ướt lâu là chỗ nấm mọc.</li>
        </ul>
        @break

    @case(\App\Enums\ShoppingIntent::Event)
        <p class="intent-page__lead">
            Hoa sự kiện khác hoa lẻ ở <strong>thời gian và số lượng</strong>. Đặt trước
            và khai đủ thông tin thì cửa hàng gom được đúng loại hoa bạn cần.
        </p>

        <h2 class="intent-page__heading">1. Đặt trước bao lâu</h2>
        <ul class="intent-page__list">
            <li><strong>Lẵng khai trương lẻ</strong> — trước 1 ngày là đủ.</li>
            <li><strong>Từ 10 lẵng trở lên, hoặc hoa theo tông màu riêng</strong> — trước 3–5 ngày. Hoa nhập theo màu cụ thể phải đặt từ vườn.</li>
            <li><strong>Đám cưới, hội nghị</strong> — trước 1–2 tuần, vì còn phải chốt mẫu và đi khảo sát chỗ đặt.</li>
        </ul>

        <h2 class="intent-page__heading">2. Khai gì khi gửi yêu cầu</h2>
        <p class="intent-page__text">
            Biểu mẫu <a href="{{ route('shop.bulk-inquiry.create') }}">Sự kiện &amp; số lượng lớn</a>
            có nhiều ô nhưng <strong>không ô nào bắt buộc</strong> ngoài tên và số điện thoại.
            Điền được ô nào thì cửa hàng đỡ phải gọi hỏi ô đó — đặc biệt là
            <em>ngày cần hoa</em>, <em>địa điểm</em> và <em>khoảng ngân sách</em>.
        </p>

        <h2 class="intent-page__heading">3. Lưu ý khi giao</h2>
        <ul class="intent-page__list">
            <li>Cho cửa hàng biết <strong>giờ khai mạc</strong>, không chỉ ngày — lẵng hoa nên tới trước 1–2 tiếng.</li>
            <li>Toà nhà có quy định ra vào thì báo trước, shipper cần thời gian đăng ký.</li>
            <li>Băng rôn cần <strong>đúng chính tả tên công ty</strong> — ghi rõ trong ô nội dung, cửa hàng in đúng như bạn gõ.</li>
        </ul>
        @break

    @case(\App\Enums\ShoppingIntent::Beginner)
        <p class="intent-page__lead">
            Phần lớn người bỏ cuộc sau cây đầu tiên vì chọn nhầm loài khó, rồi kết luận
            <em>tôi không trồng được cây</em>. Cây đầu tiên nên là cây
            <strong>khó chết nhất</strong>, không phải cây đẹp nhất.
        </p>

        <h2 class="intent-page__heading">1. Bắt đầu bằng ba loài này</h2>
        <ul class="intent-page__list">
            <li><strong>Lưỡi hổ</strong> — chịu được quên tưới cả tháng, sống cả ở góc tối.</li>
            <li><strong>Trầu bà</strong> — chịu bóng, lá vàng là biết ngay đang thừa nước.</li>
            <li><strong>Sen đá</strong> — chỉ cần nắng và đừng tưới nhiều; hợp bệ cửa sổ.</li>
        </ul>

        <h2 class="intent-page__heading">2. Lỗi số một: tưới quá nhiều</h2>
        <p class="intent-page__text">
            Cây trong nhà chết vì úng nhiều hơn vì khô. Rễ ngâm nước sẽ thối, mà lá vàng
            do thối rễ trông <em>giống hệt</em> lá vàng do thiếu nước — nên người mới
            thấy vàng lá lại tưới thêm, và cây chết nhanh hơn.
        </p>
        <p class="intent-page__text">
            <strong>Cách kiểm tra:</strong> cắm ngón tay xuống đất 2–3cm. Còn ẩm thì chưa
            tưới. Chỉ vậy thôi.
        </p>

        <h2 class="intent-page__heading">3. Bốn thứ nên mua cùng cây đầu tiên</h2>
        <ol class="intent-page__list">
            <li><strong>Chậu có lỗ thoát nước</strong> — chậu bịt kín là cách chắc chắn nhất để úng rễ.</li>
            <li><strong>Đĩa lót</strong> — hứng nước thừa, giữ sàn khô.</li>
            <li><strong>Viên đất nung</strong> — rải đáy chậu, nước thoát nhanh hơn hẳn.</li>
            <li><strong>Bình tưới vòi dài</strong> — tưới vào gốc, không ướt lá.</li>
        </ol>

        <h2 class="intent-page__heading">4. Cửa hàng nhắc bạn tưới</h2>
        <p class="intent-page__text">
            Mua cây có tài khoản thì sau khi giao xong, hệ thống tự tạo
            <a href="{{ route('shop.care.index') }}">lịch chăm cây</a> theo đúng chu kỳ của
            loài đó và gửi thư nhắc lúc 8 giờ sáng ngày tới hạn. Tắt được bất cứ lúc nào,
            tắt riêng từng cây cũng được.
        </p>
        @break

@endswitch

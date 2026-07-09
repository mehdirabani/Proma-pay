<?php

class EcommerceController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        if (Auth::role() === 'admin') {
            redirect('ecommerce/products');
        }
        redirect('ecommerce/shop');
    }

    public function addProduct()
    {
        $this->requireRole('admin');
        $this->render('ecommerce/add-product', [
            'title' => 'افزودن محصول',
            'product' => null,
        ]);
    }

    public function editProduct($id)
    {
        $this->requireRole('admin');
        $product = Ecommerce::findProduct((int) $id);
        if (!$product) {
            set_flash('error', 'محصول پیدا نشد.');
            redirect('ecommerce/products');
        }
        $this->render('ecommerce/add-product', [
            'title' => 'ویرایش محصول',
            'product' => $product,
        ]);
    }

    public function storeProduct()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $id = Ecommerce::saveProduct($_POST, $_FILES['image'] ?? null, Auth::id());
            set_flash('success', !empty($_POST['id']) ? 'محصول به‌روزرسانی شد.' : 'محصول جدید ثبت شد.');
            redirect('ecommerce/editProduct/' . $id);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect(!empty($_POST['id']) ? 'ecommerce/editProduct/' . (int) $_POST['id'] : 'ecommerce/addProduct');
        }
    }

    public function products()
    {
        $this->requireRole('admin');
        $result = Ecommerce::products([
            'q' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'page' => max(1, (int) to_english_digits($_GET['page'] ?? 1)),
            'per_page' => 24,
        ]);
        $this->render('ecommerce/products', [
            'title' => 'فهرست محصولات',
            'products' => $result['items'],
            'pagination' => $result,
        ]);
    }

    public function orders()
    {
        $this->requireRole('admin');
        $this->render('ecommerce/orders', [
            'title' => 'فهرست سفارشات',
            'orders' => Ecommerce::orders(),
            'installmentRequests' => Ecommerce::installmentRequests(),
        ]);
    }

    public function shop()
    {
        Auth::requireLogin();
        $result = Ecommerce::activeProducts([
            'q' => $_GET['q'] ?? '',
            'category' => $_GET['category'] ?? '',
            'page' => max(1, (int) to_english_digits($_GET['page'] ?? 1)),
            'per_page' => 24,
        ]);
        $this->render('ecommerce/shop', [
            'title' => 'فروشگاه',
            'products' => $result['items'],
            'pagination' => $result,
            'cartSummary' => Ecommerce::cartSummary(),
        ]);
    }

    public function product($identifier = null)
    {
        Auth::requireLogin();
        $product = Ecommerce::findProduct($identifier ?? '');
        if (!$product || (($product['status'] ?? '') !== 'active' && Auth::role() !== 'admin')) {
            $this->notFound();
        }
        $this->render('ecommerce/product', [
            'title' => $product['title'],
            'product' => $product,
            'cartSummary' => Ecommerce::cartSummary(),
        ]);
    }

    public function cart()
    {
        Auth::requireLogin();
        $this->render('ecommerce/cart', [
            'title' => 'سبد خرید',
            'cartSummary' => Ecommerce::cartSummary(),
        ]);
    }

    public function addToCart($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            Ecommerce::addToCart((int) $id, $_POST['quantity'] ?? 1);
            set_flash('success', 'محصول به سبد خرید اضافه شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('ecommerce/cart');
    }

    public function updateCart()
    {
        Auth::requireLogin();
        $this->onlyPost();
        Ecommerce::updateCart($_POST['quantities'] ?? []);
        set_flash('success', 'سبد خرید به‌روزرسانی شد.');
        redirect('ecommerce/cart');
    }

    public function checkout()
    {
        Auth::requireLogin();
        $summary = Ecommerce::cartSummary();
        if (!$summary['items']) {
            set_flash('error', 'سبد خرید خالی است.');
            redirect('ecommerce/shop');
        }
        User::ensureProfileColumns();
        $this->render('ecommerce/checkout', [
            'title' => 'تسویه حساب',
            'cartSummary' => $summary,
            'user' => User::find(Auth::id()) ?: Auth::user(),
        ]);
    }

    public function placeOrder()
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            $orderId = Ecommerce::createOrderFromCart(Auth::id(), $_POST);
            set_flash('success', 'سفارش شما ثبت شد.');
            redirect('ecommerce/payment/' . $orderId);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('ecommerce/checkout');
        }
    }

    public function payment($id)
    {
        Auth::requireLogin();
        $order = Ecommerce::findOrder((int) $id);
        if (!$order || (Auth::role() !== 'admin' && (int) ($order['customer_id'] ?? 0) !== (int) Auth::id())) {
            $this->notFound();
        }
        $this->render('ecommerce/payment', [
            'title' => 'پرداخت سفارش',
            'order' => $order,
        ]);
    }

    public function installmentRequest($identifier = null)
    {
        Auth::requireLogin();
        $product = $identifier ? Ecommerce::findProduct($identifier) : null;
        User::ensureProfileColumns();
        $user = User::find(Auth::id()) ?: Auth::user();
        $this->render('ecommerce/installment-request', [
            'title' => 'درخواست خرید اقساطی',
            'product' => $product,
            'user' => $user,
        ]);
    }

    public function storeInstallmentRequest()
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            Ecommerce::createInstallmentRequest($_POST, Auth::id());
            set_flash('success', 'درخواست خرید اقساطی ثبت شد و برای بررسی مدیریت ارسال شد.');
            redirect('ecommerce/shop');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('ecommerce/installmentRequest' . (!empty($_POST['product_id']) ? '/' . (int) $_POST['product_id'] : ''));
        }
    }

    protected function notFound()
    {
        http_response_code(404);
        $this->render('errors/404', ['title' => 'صفحه پیدا نشد'], 'app');
        exit;
    }
}

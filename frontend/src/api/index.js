import request from '../utils/request'

export const login = (data) => request.post('/auth/login', data)
export const getProfile = () => request.get('/auth/me')

export const getGoodsList = () => request.get('/goods')
export const getGoodsDetail = (id) => request.get(`/goods/${id}`)

export const getCart = () => request.get('/cart')
export const addCart = (data) => request.post('/cart', data)
export const updateCart = (id, data) => request.put(`/cart/${id}`, data)
export const deleteCart = (id) => request.delete(`/cart/${id}`)
export const previewCart = (cartIds) => request.post('/cart/preview', { cart_ids: cartIds })

export const createOrder = (cartIds, requestId) =>
  request.post('/orders', { cart_ids: cartIds }, { headers: { 'X-Request-Id': requestId } })
export const getOrders = () => request.get('/orders')
export const getOrderDetail = (orderNo) => request.get(`/orders/${orderNo}`)
export const payOrder = (orderNo) => request.post(`/orders/${orderNo}/pay`)
export const payCallback = (orderNo) => request.post('/payments/callback', { order_no: orderNo })

export const getSeckillList = () => request.get('/seckill')
export const getSeckillDetail = (id) => request.get(`/seckill/${id}`)
export const buySeckill = (id) => request.post(`/seckill/${id}/buy`)

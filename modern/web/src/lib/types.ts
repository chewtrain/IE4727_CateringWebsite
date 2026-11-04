// Shapes of the JSON the Go API returns (see modern/api/store.go and handlers.go).

export interface Package {
  id: number
  name: string
  tagline: string
  description: string
  pricePerPax: number
  minPax: number
  image: string // relative path in the shared base/images folder, e.g. "images/pkg-nusantara.jpg"
  imageAlt: string
  isFeatured: boolean
  dishCount: number // total dishes a customer picks
}

export interface Dish {
  id: number
  name: string
  description: string
  isVegetarian: boolean
  isSpicy: boolean
}

export interface MenuCategory {
  id: number
  name: string
  choose: number
  dishes: Dish[]
}

export interface PackageDetail {
  package: Package
  menu: MenuCategory[]
}

export interface Rules {
  siteName: string
  contactPhone: string
  contactEmail: string
  leadDays: number
  maxGuests: number
  deliveryFee: number
  freeDeliveryMin: number
  timeSlots: string[]
  earliestDate: string
  latestDate: string
  statusLabels: Record<string, string>
}

export type OrderStatus =
  | 'pending_payment'
  | 'payment_failed'
  | 'confirmed'
  | 'preparing'
  | 'out_for_delivery'
  | 'delivered'
  | 'cancelled'

export interface OrderItem {
  packageName: string
  pricePerPax: number
  guests: number
  lineTotal: number
  image: string
  dishes: { name: string; category: string }[]
}

export interface HistoryEvent {
  status: OrderStatus
  label: string
  note: string
  changedAt: string // "YYYY-MM-DD HH:MM:SS", Singapore time
}

export interface Order {
  ref: string
  customerName: string
  email: string
  phone: string
  address: string
  postalCode: string
  eventDate: string
  timeSlot: string
  notes: string
  subtotal: number
  deliveryFee: number
  total: number
  status: OrderStatus
  statusLabel: string
  payable: boolean
  createdAt: string
  items: OrderItem[]
  history: HistoryEvent[]
}

export interface CustomerDetails {
  name: string
  email: string
  emailConfirm: string
  phone: string
  address: string
  postalCode: string
  eventDate: string
  timeSlot: string
  notes: string
  terms: boolean
}

export type CustomerField = keyof CustomerDetails

export interface ApiErrorBody {
  error: string
  fields?: Partial<Record<CustomerField, string>>
  items?: Record<string, string[]>
}

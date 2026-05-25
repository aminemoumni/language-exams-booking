import { redirect } from 'next/navigation'

/** Home → redirect to sessions list */
export default function Home() {
  redirect('/sessions')
}

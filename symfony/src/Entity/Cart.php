<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ["persist"])]
    private Collection $products;

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: Checkout::class)]
    private Collection $checkouts;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->checkouts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(CartItem $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setCart($this);
        }

        return $this;
    }

    public function removeProduct(CartItem $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getCart() === $this) {
                $product->setCart(null);
            }
        }

        return $this;
    }

    public function setProducts($products): void
    {
        $this->clearProducts();
        foreach ($products as $product) {
            if ($product->isTicket()) {
                $item = new CartItem();
                $item->setProduct($product);
                $item->setQuantity(0);
                $this->addProduct($item);
            }
        }
    }

    public function clearProducts(): void
    {
        $this->products = new ArrayCollection();
    }

    /**
     * @return Collection<int, Checkout>
     */
    public function getCheckouts(): Collection
    {
        return $this->checkouts;
    }

    public function addCheckout(Checkout $checkout): static
    {
        if (!$this->checkouts->contains($checkout)) {
            $this->checkouts->add($checkout);
            $checkout->setCart($this);
        }

        return $this;
    }

    public function removeCheckout(Checkout $checkout): static
    {
        if ($this->checkouts->removeElement($checkout)) {
            // set the owning side to null (unless already changed)
            if ($checkout->getCart() === $this) {
                $checkout->setCart(null);
            }
        }

        return $this;
    }
}

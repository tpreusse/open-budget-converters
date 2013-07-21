# encoding: UTF-8

module OpenBudget
  class Node
    attr_accessor :id, :name, :short_name, :detail, :children, :parent
    attr_reader :balances

    def initialize
      @balances = {}
      @children = []
    end

    def touch
      @dirty = true
      if parent
        parent.touch
      end
    end

    def name=(value)
      if @name.present? && @name.downcase != value.downcase
        warn "name diff detected #{value} for #{@name} (#{id})"
      end
      @name = value
    end

    def prepare
      if @dirty
        puts "#{id} cleaning"
        if @children.empty?
          puts " - leaf node was dirty"
        else
          sums = {}
          children.each do |child|
            child.prepare
            child.balances.each do |balance_key, balance|
              sum = sums[balance_key] ||= Balance.new
              sum += balance
            end
          end

          sums.each do |key, sum|
            if sum != balances[key]
              if balances[key] == nil
                puts " - #{key}: created aggregate"
              else
                puts " - #{key}: diff detected sum #{sum.accounts.to_s} orig #{balances[key].accounts.to_s}"
              end
              balances[key] = sum
            end
          end
        end

        @dirty = false
      end
    end

    def as_hash_without_children
      hash = {
        id: id,
        name: name,
        detail: !!detail
      }
      hash[:short_name] = short_name unless short_name.blank?

      prepare

      balances.each do |key, balance|
        hash[key] = balance unless balance.empty?
      end

      hash
    end

    def as_json(options = nil)
      json = as_hash_without_children
      json[:children] = children unless children.empty?
      json
    end

    def self.from_hash(hash, parent = nil)
      node = new
      node.id = hash[:id]
      node.name = hash[:name]
      node.detail = hash[:detail]
      node.short_name = hash[:short_name]
      node.parent = parent

      hash[:children].to_a.each do |child|
        node.children << Node.from_hash(child, node)
      end

      hash.each do |key, value|
        if value.is_a? Hash
          balance = node.balances[key.to_sym] ||= Balance.new
          balance.load_hash value
        end
      end

      node
    end

    def add(balance_name, type, year, amount)
      balance = balances[balance_name.to_sym] ||= Balance.new
      balance.add(type, year, amount)
    end

    def add_gross_cost(type, year, amount)
      if amount < 0
        add_revenue(type, year, amount * -1)
        # parent.touch # disabled propagating, needs more thought
      else
        add(:gross_cost, type, year, amount)
      end
    end

    def add_revenue(type, year, amount)
      if amount < 0
        add_gross_cost(type, year, amount * -1)
        # parent.touch # disabled propagating, needs more thought
      else
        add(:revenue, type, year, amount)
      end
    end
  end
end
